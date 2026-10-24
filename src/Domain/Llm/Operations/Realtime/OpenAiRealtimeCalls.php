<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Realtime;

use Domain\Llm\Contracts\RealtimeCallHandler;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Data\Realtime\RealtimeCallData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Exceptions\RealtimeCallFailedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use Throwable;

/**
 * Voice calls on the OpenAI Realtime API, with the OpenAI key and address of
 * Settings → AI providers.
 *
 * The browser's SDP offer is posted with the session settings to
 * `/realtime/calls` (OpenAI's "unified interface"), so the browser never
 * holds a key; the answer's `Location` header names the call. A worker then
 * joins the call over `wss://…/realtime?call_id=…` (the "sideband"), which
 * receives the call's events and steers it. The socket runs on the ReactPHP
 * loop Reverb already installs; {@see join()} runs the loop until the call
 * ends.
 */
class OpenAiRealtimeCalls implements RealtimeCalls
{
    /** Seconds to wait for OpenAI to answer the offer. */
    private const int CREATE_TIMEOUT = 15;

    public function isEnabled(): bool
    {
        return AiProvider::OpenAi->isConnected();
    }

    public function model(): string
    {
        return (string) config('services.openai.realtime_model');
    }

    public function create(string $offerSdp, array $session, string $safetyId): RealtimeCallData
    {
        try {
            $response = Http::withToken((string) config('services.openai.api_key'))
                ->withHeaders(['OpenAI-Safety-Identifier' => $safetyId])
                ->timeout(self::CREATE_TIMEOUT)
                ->asMultipart()
                ->post($this->endpoint().'/realtime/calls', [
                    'sdp' => $this->sdpLines($offerSdp),
                    'session' => json_encode($session, JSON_THROW_ON_ERROR),
                ]);
        } catch (ConnectionException $e) {
            throw new RealtimeCallFailedException('OpenAI did not answer the voice call: '.$e->getMessage(), $e->getCode(), previous: $e);
        }

        if ($response->failed()) {
            throw new RealtimeCallFailedException(sprintf(
                'OpenAI refused the voice call (HTTP %d): %s',
                $response->status(),
                Str::limit($response->body(), 500),
            ));
        }

        // `/v1/realtime/calls/rtc_…`: the segment after calls/ is the call's id.
        $location = Str::rtrim($response->header('Location'), '/');
        $callId = Str::contains($location, '/calls/') ? Str::afterLast($location, '/') : '';

        if (blank($callId) || blank($response->body())) {
            throw new RealtimeCallFailedException('OpenAI answered the voice call without an answer or a call id.');
        }

        return new RealtimeCallData(answerSdp: $response->body(), callId: $callId);
    }

    public function join(string $callId, RealtimeCallHandler $handler): void
    {
        $loop = Loop::get();
        $failure = null;

        new Connector($loop)(
            $this->socketUrl().'?call_id='.rawurlencode($callId),
            [],
            ['Authorization' => 'Bearer '.config('services.openai.api_key')],
        )->then(
            function (WebSocket $socket) use ($handler, $loop): void {
                $timer = $loop->addPeriodicTimer(1.0, fn () => $this->safely(static fn () => $handler->tick()));

                $socket->on('message', function (MessageInterface $message) use ($handler): void {
                    $event = json_decode((string) $message, true);

                    if (is_array($event)) {
                        $this->safely(static fn () => $handler->received($event));
                    }
                });

                $socket->on('close', function () use ($handler, $loop, $timer): void {
                    $loop->cancelTimer($timer);
                    $this->safely(static fn () => $handler->closed());
                    $loop->stop();
                });

                $handler->opened(new OpenAiRealtimeConnection($socket));
            },
            static function (Throwable $e) use (&$failure, $loop): void {
                $failure = $e;
                $loop->stop();
            },
        );

        $loop->run();

        if ($failure instanceof Throwable) {
            throw new RealtimeCallFailedException('Could not join the voice call: '.$failure->getMessage(), previous: $failure);
        }
    }

    public function hangUp(string $callId): void
    {
        try {
            Http::withToken((string) config('services.openai.api_key'))
                ->timeout(10)
                ->post($this->endpoint().'/realtime/calls/'.rawurlencode($callId).'/hangup');
        } catch (Throwable $e) {
            // The call may be over already; nothing more can be done here.
            report($e);
        }
    }

    /**
     * The offer as SDP wants it: every line ends with CRLF, the last one too.
     * Form input is trimmed on the way in (Laravel's TrimStrings), which cuts
     * the final line break, and OpenAI then fails to read the offer ("failed
     * to unmarshal SDP: EOF").
     */
    private function sdpLines(string $sdp): string
    {
        return Str::of($sdp)
            ->trim()
            ->split('/\r\n|\n|\r/')
            ->implode("\r\n")."\r\n";
    }

    /**
     * Run one handler step. A bug in one turn must not end the visitor's
     * call (an uncaught error would stop the loop), so it is reported and
     * the call goes on.
     */
    private function safely(callable $step): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function endpoint(): string
    {
        return Str::rtrim((string) config('services.openai.api_endpoint'), '/');
    }

    /** The API address as a WebSocket address: https://…/v1 → wss://…/v1/realtime. */
    private function socketUrl(): string
    {
        return Str::replaceMatches('#^http#i', 'ws', $this->endpoint()).'/realtime';
    }
}
