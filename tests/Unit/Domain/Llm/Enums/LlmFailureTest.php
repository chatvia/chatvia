<?php

declare(strict_types=1);

use Domain\Llm\Enums\LlmFailure;
use Domain\Llm\Exceptions\NoLlmAvailableException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

/**
 * An AI call's exception becomes the kind of failure an admin can act on,
 * from Laravel's HTTP client and from Guzzle (streamed calls) alike.
 */
function lfFailed(int $status, string $body = '{}'): RequestException
{
    return new RequestException(new Response(new PsrResponse($status, [], $body)));
}

it('reads the status of a failed call', function (int $status, LlmFailure $failure): void {
    expect(LlmFailure::of(lfFailed($status)))->toBe($failure);
})->with([
    'not served' => [404, LlmFailure::ModelUnavailable],
    'unauthorized' => [401, LlmFailure::KeyRefused],
    'forbidden' => [403, LlmFailure::KeyRefused],
    'payment required' => [402, LlmFailure::OutOfCredit],
    'too many requests' => [429, LlmFailure::RateLimited],
    'server error' => [503, LlmFailure::ProviderError],
    'bad request' => [400, LlmFailure::Other],
]);

it('reads an empty balance from the error body', function (): void {
    expect(LlmFailure::of(lfFailed(400, '{"error":{"message":"Your credit balance is too low to access the Anthropic API."}}')))->toBe(LlmFailure::OutOfCredit)
        ->and(LlmFailure::of(lfFailed(429, '{"error":{"code":"insufficient_quota"}}')))->toBe(LlmFailure::OutOfCredit);
});

it('reads a connection failure and a missing model', function (): void {
    expect(LlmFailure::of(new ConnectionException('timed out')))->toBe(LlmFailure::Unreachable)
        ->and(LlmFailure::of(new ConnectException('refused', new PsrRequest('POST', 'https://ai.test'))))->toBe(LlmFailure::Unreachable)
        ->and(LlmFailure::of(new NoLlmAvailableException))->toBe(LlmFailure::NoModel)
        ->and(LlmFailure::of(new RuntimeException('boom')))->toBe(LlmFailure::Other);
});

it('reads a failed streamed call', function (): void {
    $e = new ClientException('not found', new PsrRequest('POST', 'https://ai.test'), new PsrResponse(404));

    expect(LlmFailure::of($e))->toBe(LlmFailure::ModelUnavailable);
});
