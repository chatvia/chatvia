<?php

use Domain\Llm\Data\Realtime\RealtimeCallData;
use Domain\Llm\Exceptions\RealtimeCallFailedException;
use Domain\Llm\Operations\Realtime\OpenAiRealtimeCalls;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

/**
 * Opening and ending a voice call on the OpenAI Realtime API. The browser's
 * offer goes to OpenAI from the server (the browser never holds the key); the
 * answer's Location header names the call. Every way the call can fail to
 * open ends in RealtimeCallFailedException, so the caller shows one "try
 * again" message.
 */
beforeEach(function (): void {
    config([
        'services.openai.api_key' => 'sk-test',
        'services.openai.api_endpoint' => 'https://openai.test/v1/',
    ]);
});

it('posts the offer and the session as multipart, with the key and the safety id', function (): void {
    Http::fake(['openai.test/v1/realtime/calls' => Http::response("v=0\r\nanswer\r\n", 201, ['Location' => '/v1/realtime/calls/rtc_abc123'])]);
    $session = ['type' => 'realtime', 'model' => 'gpt-realtime', 'instructions' => 'Be brief.'];

    // Form input arrives trimmed and with bare line feeds; SDP wants CRLF on
    // every line, the last one too.
    $call = app(OpenAiRealtimeCalls::class)->create("v=0\no=- 1 2 IN IP4 127.0.0.1\ns=-\n\n", $session, 'visitor-hash');

    expect($call)->toEqual(new RealtimeCallData(answerSdp: "v=0\r\nanswer\r\n", callId: 'rtc_abc123'));

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($session): bool {
        $parts = collect($request->data())->pluck('contents', 'name');

        return $request->method() === 'POST'
            && $request->url() === 'https://openai.test/v1/realtime/calls'
            && $request->isMultipart()
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request->hasHeader('OpenAI-Safety-Identifier', 'visitor-hash')
            && $parts->get('sdp') === "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\ns=-\r\n"
            && $parts->get('session') === json_encode($session);
    });
});

it('takes the call id from the last segment of the Location header', function (string $location): void {
    Http::fake(['*' => Http::response('answer', 201, ['Location' => $location])]);

    expect(app(OpenAiRealtimeCalls::class)->create('v=0', [], 'visitor-hash')->callId)->toBe('rtc_abc123');
})->with([
    'a path' => '/v1/realtime/calls/rtc_abc123',
    'a full address' => 'https://api.openai.com/v1/realtime/calls/rtc_abc123',
    'a trailing slash' => '/v1/realtime/calls/rtc_abc123/',
]);

it('fails the call when OpenAI refuses it', function (): void {
    Http::fake(['*' => Http::response('{"error":"bad offer"}', 400)]);

    expect(fn (): RealtimeCallData => app(OpenAiRealtimeCalls::class)->create('v=0', [], 'visitor-hash'))
        ->toThrow(RealtimeCallFailedException::class, 'OpenAI refused the voice call (HTTP 400): {"error":"bad offer"}');
});

it('fails the call when OpenAI cannot be reached', function (): void {
    Http::fake(['*' => Http::failedConnection('connection timed out')]);

    expect(fn (): RealtimeCallData => app(OpenAiRealtimeCalls::class)->create('v=0', [], 'visitor-hash'))
        ->toThrow(function (RealtimeCallFailedException $e): void {
            expect($e->getMessage())->toBe('OpenAI did not answer the voice call: connection timed out')
                ->and($e->getPrevious())->toBeInstanceOf(ConnectionException::class);
        });
});

it('fails the call when the answer has no call id or no SDP', function (string $body, array $headers): void {
    Http::fake(['*' => Http::response($body, 201, $headers)]);

    expect(fn (): RealtimeCallData => app(OpenAiRealtimeCalls::class)->create('v=0', [], 'visitor-hash'))
        ->toThrow(RealtimeCallFailedException::class, 'OpenAI answered the voice call without an answer or a call id.');
})->with([
    'no Location header' => ['answer', []],
    'an empty answer' => ['', ['Location' => '/v1/realtime/calls/rtc_abc123']],
]);

it('hangs up the call with the key', function (): void {
    Http::fake(['*' => Http::response()]);

    app(OpenAiRealtimeCalls::class)->hangUp('rtc_abc123');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://openai.test/v1/realtime/calls/rtc_abc123/hangup'
        && $request->hasHeader('Authorization', 'Bearer sk-test'));
});

it('reports a failed hang-up instead of throwing, as the call may be over already', function (): void {
    Exceptions::fake();
    Http::fake(['*' => Http::failedConnection()]);

    app(OpenAiRealtimeCalls::class)->hangUp('rtc_abc123');

    Exceptions::assertReported(ConnectionException::class);
});
