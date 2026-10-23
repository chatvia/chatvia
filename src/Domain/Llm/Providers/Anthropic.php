<?php

declare(strict_types=1);

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;
use Domain\Llm\Providers\Messages\MessagesPayload;
use Domain\Llm\Providers\Messages\MessagesReply;
use Domain\Llm\Providers\Messages\MessagesStream;
use Domain\Llm\Services\SharedHttpHandler;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Promises\LazyPromise;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Claude through Anthropic's own Messages API (`/messages`), not its
 * OpenAI-style endpoint, which leaves out prompt caching, PDFs and the
 * thinking. The app still speaks Chat Completions: each request is written
 * in Messages form ({@see MessagesPayload}) and each reply read back as
 * Chat Completions ({@see MessagesStream}, {@see MessagesReply}).
 *
 * A reasoning effort asks for adaptive thinking. The older models, which
 * think only on a token budget, refuse it; they are asked again with a
 * budget, and asked that way for a while.
 */
class Anthropic extends AbstractLlmProvider
{
    /** The API version every request names. */
    public const string VERSION = '2023-06-01';

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $settings
     */
    public function stream(string $model, array $messages, array $settings = []): ResponseInterface
    {
        $showsReasoning = (bool) ($settings[self::SHOW_REASONING] ?? false);
        unset($settings[self::SHOW_REASONING]);

        $body = MessagesPayload::fromChatCompletions(
            $this->preparePayload([...['model' => $model, 'messages' => $messages], ...$settings, 'stream' => true]),
            $showsReasoning,
        );

        if (Cache::has($this->budgetKey($body))) {
            return $this->streamMessages(MessagesPayload::withThinkingBudget($body));
        }

        try {
            return $this->streamMessages($body);
        } catch (ClientException $e) {
            if (! isset($body['thinking']) || ! $this->refusesAdaptiveThinking($e->getResponse()->getStatusCode(), (string) $e->getResponse()->getBody())) {
                throw $e;
            }

            Cache::put($this->budgetKey($body), true, now()->addHour());

            return $this->streamMessages(MessagesPayload::withThinkingBudget($body));
        }
    }

    /**
     * @param  array<string, mixed>  $data  a Chat Completions body
     * @return Response a Chat Completions reply
     */
    public function completion(array $data): Response
    {
        $body = $this->messagesBody($data);
        $response = $this->send($body);

        if (isset($body['thinking']) && $this->refusesAdaptiveThinking($response->status(), $response->body())) {
            Cache::put($this->budgetKey($body), true, now()->addHour());
            $response = $this->send(MessagesPayload::withThinkingBudget($body));
        }

        return $this->asChatCompletion($response->throw());
    }

    /**
     * @param  array<string, mixed>  $data  a Chat Completions body
     * @return PromiseInterface resolving to a Chat Completions reply
     */
    public function completionAsync(array $data): PromiseInterface
    {
        $body = $this->messagesBody($data);

        return $this->sendAsync($body)->then(function (Response $response) use ($body): Response|PromiseInterface {
            if (isset($body['thinking']) && $this->refusesAdaptiveThinking($response->status(), $response->body())) {
                Cache::put($this->budgetKey($body), true, now()->addHour());

                return $this->sendAsync(MessagesPayload::withThinkingBudget($body))
                    ->then(fn (Response $retry): Response => $this->asChatCompletion($retry->throw()));
            }

            return $this->asChatCompletion($response->throw());
        });
    }

    /**
     * The key and API version instead of a bearer key, JSON, and the
     * provider's own extra headers, which win.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return array_merge(
            ['x-api-key' => $this->getApiKey(), 'anthropic-version' => self::VERSION, 'Content-Type' => 'application/json'],
            $this->connection->headers ?? [],
        );
    }

    public function getCompletionEndpoint(): string
    {
        return $this->getBaseUrl().'/messages';
    }

    /**
     * A single call's body in Messages form, on a token budget when the
     * model is known to need one.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function messagesBody(array $data): array
    {
        unset($data[self::SHOW_REASONING], $data['stream']);
        $body = MessagesPayload::fromChatCompletions($this->preparePayload($data));

        return Cache::has($this->budgetKey($body)) ? MessagesPayload::withThinkingBudget($body) : $body;
    }

    /**
     * @param  array<string, mixed>  $body  a Messages body
     */
    private function streamMessages(array $body): ResponseInterface
    {
        $response = app(GuzzleClient::class)->post($this->getCompletionEndpoint(), [
            'headers' => $this->headers(),
            'json' => $body,
            'stream' => true,
            'read_timeout' => 120,
        ]);

        return $response->withBody(new MessagesStream($response->getBody()));
    }

    /**
     * Send one call and keep the reply, error or not, so a refused thinking
     * mode can be asked again.
     *
     * @param  array<string, mixed>  $body  a Messages body
     */
    private function send(array $body): Response
    {
        return $this->client()->timeout(600)->post($this->getCompletionEndpoint(), $body);
    }

    /**
     * Send one call on the shared handler ({@see SharedHttpHandler}), so it
     * runs at the same time as the request's other async AI calls. The
     * promise rejects only when the connection fails.
     *
     * @param  array<string, mixed>  $body  a Messages body
     */
    private function sendAsync(array $body): PromiseInterface
    {
        /** @var LazyPromise $pending */
        $pending = $this->client()
            ->setHandler(app(SharedHttpHandler::class)->handler())
            ->async()
            ->timeout(600)
            ->post($this->getCompletionEndpoint(), $body);

        return $pending->buildPromise()->then(static function (Response|Throwable $response): Response {
            if ($response instanceof Throwable) {
                throw $response;
            }

            return $response;
        });
    }

    /** The reply with its body written as a Chat Completions reply. */
    private function asChatCompletion(Response $response): Response
    {
        $json = (string) json_encode(MessagesReply::toChatCompletion((array) $response->json()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new Response($response->toPsrResponse()->withBody(Utils::streamFor($json)));
    }

    /**
     * Whether a reply turned down adaptive thinking or the effort: the
     * models before them take only a thinking budget.
     */
    private function refusesAdaptiveThinking(int $status, string $body): bool
    {
        return $status === 400 && Str::contains($body, ['adaptive', 'effort'], ignoreCase: true);
    }

    /**
     * @param  array<string, mixed>  $body  a Messages body
     */
    private function budgetKey(array $body): string
    {
        return 'llm:anthropic:thinking-budget:'.$this->connection->getKey().':'.($body['model'] ?? '');
    }
}
