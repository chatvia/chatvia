<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Responses;

use Domain\Llm\AbstractLlmProvider;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Streams chat turns through the provider's Responses API (`/responses`)
 * instead of Chat Completions. A provider that has one uses this trait: the
 * request is written in Responses form ({@see ResponsesPayload}) after the
 * provider's own preparePayload(), and the reply is read back as a Chat
 * Completions stream ({@see ResponsesStream}), so the chat engine sees no
 * difference. Non-streamed calls (completion()) stay on Chat Completions.
 *
 * A turn that shows the model's thinking
 * ({@see AbstractLlmProvider::SHOW_REASONING}) asks for a summary of it. A
 * provider that refuses summaries (OpenAI before it verifies the
 * organization) is asked again without one, and not asked for a while.
 *
 * @phpstan-require-extends AbstractLlmProvider
 */
trait StreamsThroughResponses
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $settings
     */
    public function stream(string $model, array $messages, array $settings = []): ResponseInterface
    {
        $showsReasoning = (bool) ($settings[AbstractLlmProvider::SHOW_REASONING] ?? false);
        unset($settings[AbstractLlmProvider::SHOW_REASONING]);

        $body = $this->prepareResponsesPayload(ResponsesPayload::fromChatCompletions(
            $this->preparePayload([...['model' => $model, 'messages' => $messages], ...$settings]),
        ));

        if (! $showsReasoning || ! isset($body['reasoning']) || Cache::has($this->noSummaryKey())) {
            return $this->streamResponses($body);
        }

        try {
            return $this->streamResponses(ResponsesPayload::withSummary($body));
        } catch (ClientException $e) {
            if (! $this->refusesSummary($e)) {
                throw $e;
            }

            // The model still reasons; the thinking just is not shown.
            Cache::put($this->noSummaryKey(), true, now()->addHour());

            return $this->streamResponses($body);
        }
    }

    /**
     * Adjust the Responses body for this provider before it is sent.
     * Default: no change.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function prepareResponsesPayload(array $body): array
    {
        return $body;
    }

    /**
     * @param  array<string, mixed>  $body  a Responses API body
     */
    private function streamResponses(array $body): ResponseInterface
    {
        $response = app(GuzzleClient::class)->post($this->getBaseUrl().'/responses', [
            'headers' => $this->headers(),
            'json' => $body,
            'stream' => true,
            'read_timeout' => 120,
        ]);

        return $response->withBody(new ResponsesStream($response->getBody()));
    }

    /** Whether the provider turned the request down for asking for a summary. */
    private function refusesSummary(ClientException $e): bool
    {
        return $e->getResponse()->getStatusCode() === 400
            && Str::contains((string) $e->getResponse()->getBody(), ['reasoning.summary', 'reasoning summar'], ignoreCase: true);
    }

    private function noSummaryKey(): string
    {
        return 'llm:responses:no-reasoning-summary:'.$this->connection->getKey();
    }
}
