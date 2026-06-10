<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Shared Perplexity Sonar call. Each concrete tool (web_search / ask /
 * research) only varies the model it targets and how it frames the query —
 * the request, auth, and citation parsing are identical.
 *
 * Concrete subclasses carry their own #[Name], #[Description] and read-only
 * annotations. The annotations are NOT inherited: Laravel MCP's HasAnnotations
 * reads attributes off the concrete class only (it doesn't walk parents),
 * so #[IsReadOnly] etc. must be repeated on each tool.
 */
abstract class PerplexitySonarTool extends Tool
{
    /**
     * The Sonar model this tool calls (e.g. 'sonar', 'sonar-pro').
     */
    abstract protected function model(): string;

    /**
     * Optional system steer prepended to the conversation. Null = none.
     */
    protected function systemPrompt(): ?string
    {
        return null;
    }

    /**
     * Upstream timeout in seconds. Deeper models (research) take longer.
     */
    protected function timeout(): int
    {
        return 45;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'query.required' => 'You must provide a query (what to look up on the web).',
        ]);

        // The customer supplies their own Perplexity key when they connect this
        // connector; McpClient forwards it as a Bearer token.
        $apiKey = (string) app(HttpRequest::class)->bearerToken();
        $baseUrl = rtrim((string) config('services.perplexity.base_url', 'https://api.perplexity.ai'), '/');

        if ($apiKey === '') {
            return Response::error('No Perplexity API key was provided. Reconnect the Perplexity connector with a valid key.');
        }

        $messages = [];
        if (($system = $this->systemPrompt()) !== null) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $validated['query']];

        $response = Http::withToken($apiKey)
            ->timeout($this->timeout())
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', [
                'model' => $this->model(),
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            return Response::error("Perplexity returned HTTP {$response->status()}.");
        }

        [$answer, $citations] = $this->extractAnswer($response->json());

        if ($answer === '') {
            return Response::error('Perplexity returned no answer.');
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
                ->description('The natural-language question or topic to look up on the live web, e.g. "who won the last European football cup" or "compare the latest iPhone and Pixel cameras".')
                ->required(),
        ];
    }

    /**
     * Parse Perplexity's chat-completions response into a plain answer + a list
     * of citations. Newer responses carry `search_results` (objects with title
     * + url); older ones only carry `citations` (a flat list of URLs).
     *
     * @param  array<string, mixed>|null  $body
     * @return array{0: string, 1: list<array{title: string, url: string}>}
     */
    private function extractAnswer(?array $body): array
    {
        if (! is_array($body)) {
            return ['', []];
        }

        $content = (string) ($body['choices'][0]['message']['content'] ?? '');
        // Reasoning models (sonar-reasoning*) wrap their chain-of-thought in
        // <think>…</think> before the final answer — strip it so the agent
        // only sees the answer. Harmless for non-reasoning models.
        $content = (string) preg_replace('/<think>.*?<\/think>/s', '', $content);
        $answer = trim($content);

        $citations = [];
        $seenUrls = [];

        foreach ($body['search_results'] ?? [] as $result) {
            $url = (string) ($result['url'] ?? '');
            if ($url === '' || isset($seenUrls[$url])) {
                continue;
            }
            $seenUrls[$url] = true;
            $citations[] = [
                'title' => (string) ($result['title'] ?? ''),
                'url' => $url,
            ];
        }

        // Fallback for responses that only return the legacy `citations` URLs.
        if (empty($citations)) {
            foreach ($body['citations'] ?? [] as $url) {
                $url = (string) $url;
                if ($url === '' || isset($seenUrls[$url])) {
                    continue;
                }
                $seenUrls[$url] = true;
                $citations[] = ['title' => '', 'url' => $url];
            }
        }

        return [$answer, $citations];
    }
}
