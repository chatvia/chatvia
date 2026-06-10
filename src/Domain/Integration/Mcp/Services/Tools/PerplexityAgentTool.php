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
 * Shared driver for Perplexity's Agent API tools (web_search, fetch_url,
 * finance_search, people_search, sandbox). Every tool is one POST to
 * /v1/agent with the matching entry in `tools`; the response is an `output`
 * array of tool-result items followed by the assistant message. Concrete
 * tools vary the tool type, the input framing, and (for non-query tools)
 * the schema.
 *
 * Concrete subclasses carry their own #[Name], #[Description] and read-only
 * annotations — Laravel MCP's HasAnnotations reads attributes off the
 * concrete class only, so they cannot live here.
 */
abstract class PerplexityAgentTool extends Tool
{
    /**
     * The Agent API tool type this MCP tool enables (e.g. 'web_search').
     */
    abstract protected function toolType(): string;

    /**
     * Extra config merged into the tool entry (e.g. ['max_urls' => 3]).
     *
     * @return array<string, mixed>
     */
    protected function toolConfig(): array
    {
        return [];
    }

    /**
     * Optional steer passed as the Agent API `instructions` field.
     */
    protected function instructions(): ?string
    {
        return null;
    }

    /**
     * Upstream timeout in seconds. Sandbox runs need far longer than search.
     */
    protected function timeout(): int
    {
        return 60;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationMessages(): array
    {
        return [
            'query.required' => 'You must provide a query (what to look up).',
        ];
    }

    /**
     * Build the natural-language Agent API `input` from the validated args.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function inputFor(array $validated): string
    {
        return (string) $validated['query'];
    }

    /**
     * Hook for subclasses to surface tool-specific structured content from
     * the raw `output` items (e.g. sandbox stdout/stderr).
     *
     * @param  array<int, array<string, mixed>>  $output
     * @return array<string, mixed>
     */
    protected function extraStructured(array $output): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate($this->rules(), $this->validationMessages());

        // The customer supplies their own Perplexity key when they connect this
        // connector; McpClient forwards it as a Bearer token.
        $apiKey = (string) app(HttpRequest::class)->bearerToken();
        $baseUrl = rtrim((string) config('services.perplexity.base_url', 'https://api.perplexity.ai'), '/');

        if ($apiKey === '') {
            return Response::error('No Perplexity API key was provided. Reconnect the Perplexity connector with a valid key.');
        }

        $payload = [
            'model' => (string) config('services.perplexity.agent_model', 'perplexity/sonar'),
            'input' => $this->inputFor($validated),
            'tools' => [array_merge(['type' => $this->toolType()], $this->toolConfig())],
        ];
        if (($instructions = $this->instructions()) !== null) {
            $payload['instructions'] = $instructions;
        }

        $response = Http::withToken($apiKey)
            ->timeout($this->timeout())
            ->acceptJson()
            ->post($baseUrl.'/v1/agent', $payload);

        if ($response->failed()) {
            return Response::error("Perplexity returned HTTP {$response->status()}.");
        }

        $output = $this->outputItems($response->json());
        [$answer, $citations] = $this->extractAnswer($output);

        if ($answer === '' && $citations === []) {
            return Response::error('Perplexity returned no answer.');
        }

        $summary = $answer;
        if (! empty($citations)) {
            $summary .= "\n\nSources:\n";
            foreach ($citations as $i => $cite) {
                $summary .= sprintf("[%d] %s — %s\n", $i + 1, $cite['title'] ?: $cite['url'], $cite['url']);
            }
        }

        return Response::make(Response::text($summary))->withStructuredContent(array_merge([
            'answer' => $answer,
            'citations' => $citations,
        ], $this->extraStructured($output)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The natural-language question or topic to look up, e.g. "who won the last European football cup".')
                ->required(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<int, array<string, mixed>>
     */
    private function outputItems(?array $body): array
    {
        $output = is_array($body) ? ($body['output'] ?? []) : [];

        return is_array($output) ? array_values(array_filter($output, 'is_array')) : [];
    }

    /**
     * Pull the assistant text and every cited URL out of the output items.
     * Tool-result items carry their hits under `results` (search, people,
     * finance) or `contents` (fetch_url); finance results additionally list
     * plain-URL `sources`.
     *
     * @param  array<int, array<string, mixed>>  $output
     * @return array{0: string, 1: list<array{title: string, url: string}>}
     */
    private function extractAnswer(array $output): array
    {
        $answer = '';
        $citations = [];
        $seenUrls = [];

        $push = function (string $url, string $title = '') use (&$citations, &$seenUrls): void {
            if ($url === '' || isset($seenUrls[$url])) {
                return;
            }
            $seenUrls[$url] = true;
            $citations[] = ['title' => $title, 'url' => $url];
        };

        foreach ($output as $item) {
            if (($item['type'] ?? null) === 'message' && ($item['role'] ?? null) === 'assistant') {
                foreach ((array) ($item['content'] ?? []) as $chunk) {
                    if (is_array($chunk) && isset($chunk['text'])) {
                        $answer .= (string) $chunk['text'];
                    }
                }

                continue;
            }

            foreach (['results', 'contents'] as $key) {
                foreach ((array) ($item[$key] ?? []) as $result) {
                    if (! is_array($result)) {
                        continue;
                    }
                    $push((string) ($result['url'] ?? ''), (string) ($result['title'] ?? ''));
                    foreach ((array) ($result['sources'] ?? []) as $source) {
                        if (is_string($source)) {
                            $push($source);
                        }
                    }
                }
            }
        }

        return [trim($answer), $citations];
    }
}
