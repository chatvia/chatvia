<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search the live web for up-to-date information. Returns a synthesized answer plus the source URLs the answer is built from. Use for recent events, news, prices, or any fact that may have changed since training.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class WebSearchTool extends Tool
{
    private const AGENT_CACHE_KEY = 'mcp.web_search.mistral_agent_id';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:500'],
        ], [
            'query.required' => 'You must provide a search query (what to look up on the web).',
        ]);

        // The customer supplies their own Mistral key when they connect this
        // connector; McpClient forwards it as a Bearer token.
        $apiKey = (string) app(HttpRequest::class)->bearerToken();
        $baseUrl = rtrim((string) config('services.mistral.base_url', 'https://api.mistral.ai/v1'), '/');

        if ($apiKey === '') {
            return Response::error('No Mistral API key was provided. Reconnect the Web Search connector with a valid key.');
        }

        $agentId = $this->getOrCreateAgent($apiKey, $baseUrl);
        if ($agentId === null) {
            return Response::error('Failed to provision the web-search agent on Mistral.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->acceptJson()
            ->post($baseUrl.'/conversations', [
                'inputs' => $validated['query'],
                'stream' => false,
                'agent_id' => $agentId,
            ]);

        if ($response->failed()) {
            return Response::error("Mistral web search returned HTTP {$response->status()}.");
        }

        [$answer, $citations] = $this->extractAnswer($response->json());

        if ($answer === '') {
            return Response::error('Web search returned no results.');
        }

        $summary = $answer;
        if (! empty($citations)) {
            $summary .= "\n\nSources:\n";
            foreach ($citations as $i => $cite) {
                $summary .= sprintf("[%d] %s — %s\n", $i + 1, $cite['title'] ?: $cite['url'], $cite['url']);
            }
        }

        return Response::make(Response::text($summary))->withStructuredContent([
            'answer' => $answer,
            'citations' => $citations,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('What to search the web for. A natural-language question or keyword phrase, e.g. "who won the last European football cup" or "OpenAI o3 release date".')
                ->required(),
        ];
    }

    /**
     * Lazily create (and cache) the Mistral agent that owns the web_search tool.
     * The agent is provisioned on the *customer's* Mistral account (their key),
     * so the cache is keyed per-key — one fingerprint per distinct API key —
     * rather than globally. Cached forever; flush via
     * `php artisan cache:forget {key}` if you need to recreate an agent.
     */
    private function getOrCreateAgent(string $apiKey, string $baseUrl): ?string
    {
        $cacheKey = self::AGENT_CACHE_KEY.':'.hash('sha256', $apiKey);

        return Cache::rememberForever($cacheKey, function () use ($apiKey, $baseUrl): ?string {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->acceptJson()
                ->post($baseUrl.'/agents', [
                    'model' => config('services.mistral.web_search_model', 'mistral-medium-latest'),
                    'name' => 'Chatvia Web Search',
                    'description' => 'Internal Chatvia agent that searches the web on behalf of customer chat agents.',
                    'instructions' => 'Use the `web_search` tool to find current, accurate information. Cite sources for every factual claim.',
                    'tools' => [['type' => 'web_search']],
                    'completion_args' => ['temperature' => 0.3, 'top_p' => 0.95],
                ]);

            if ($response->failed()) {
                return null;
            }

            return $response->json('id');
        });
    }

    /**
     * Parse Mistral's conversation response (list of entries) into a plain
     * answer + a list of citations.
     *
     * @param  array<string, mixed>|null  $body
     * @return array{0: string, 1: list<array{title: string, url: string, source: string}>}
     */
    private function extractAnswer(?array $body): array
    {
        $answer = '';
        $citations = [];
        $seenUrls = [];

        $entries = $body['outputs'] ?? $body['entries'] ?? [];
        if (! is_array($entries)) {
            return ['', []];
        }

        foreach ($entries as $entry) {
            if (($entry['type'] ?? null) !== 'message.output') {
                continue;
            }
            $content = $entry['content'] ?? [];
            if (is_string($content)) {
                $answer .= $content;

                continue;
            }
            if (! is_array($content)) {
                continue;
            }

            foreach ($content as $chunk) {
                $chunkType = $chunk['type'] ?? null;
                if ($chunkType === 'text' && isset($chunk['text'])) {
                    $answer .= (string) $chunk['text'];
                } elseif ($chunkType === 'tool_reference' && isset($chunk['url'])) {
                    $url = (string) $chunk['url'];
                    if (isset($seenUrls[$url])) {
                        continue;
                    }
                    $seenUrls[$url] = true;
                    $citations[] = [
                        'title' => (string) ($chunk['title'] ?? ''),
                        'url' => $url,
                        'source' => (string) ($chunk['source'] ?? $chunk['tool'] ?? 'web_search'),
                    ];
                }
            }
        }

        return [trim($answer), $citations];
    }
}
