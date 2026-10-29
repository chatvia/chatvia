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

it('reads the status and the error body of a failed call', function (int $status, string $body, LlmFailure $failure): void {
    expect(LlmFailure::of(lfFailed($status, $body)))->toBe($failure);
})->with([
    'not served' => [404, '{}', LlmFailure::ModelUnavailable],
    'unauthorized' => [401, '{}', LlmFailure::KeyRefused],
    'forbidden' => [403, '{}', LlmFailure::KeyRefused],
    'payment required' => [402, '{}', LlmFailure::OutOfCredit],
    'too many requests' => [429, '{}', LlmFailure::RateLimited],
    'server error' => [503, '{}', LlmFailure::ProviderError],
    'bad request' => [400, '{}', LlmFailure::Other],
    'an empty balance in a 400 body' => [400, '{"error":{"message":"Your credit balance is too low to access the Anthropic API."}}', LlmFailure::OutOfCredit],
    'an exhausted quota in a 429 body' => [429, '{"error":{"code":"insufficient_quota"}}', LlmFailure::OutOfCredit],
]);

it('reads a connection failure, a missing model, a failed streamed call and any other error', function (): void {
    $streamed = new ClientException('not found', new PsrRequest('POST', 'https://ai.test'), new PsrResponse(404));

    expect(LlmFailure::of(new ConnectionException('timed out')))->toBe(LlmFailure::Unreachable)
        ->and(LlmFailure::of(new ConnectException('refused', new PsrRequest('POST', 'https://ai.test'))))->toBe(LlmFailure::Unreachable)
        ->and(LlmFailure::of(new NoLlmAvailableException))->toBe(LlmFailure::NoModel)
        ->and(LlmFailure::of($streamed))->toBe(LlmFailure::ModelUnavailable)
        ->and(LlmFailure::of(new RuntimeException('boom')))->toBe(LlmFailure::Other);
});
