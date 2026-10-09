<?php

namespace App\Mcp\Tools\Perplexity;

use App\Mcp\McpCallerContext;
use Domain\Integration\Builtin\Data\Perplexity\PerplexitySonarRequestData;
use Domain\Integration\Builtin\Exceptions\PerplexityException;
use Domain\Integration\Builtin\Services\PerplexityClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Base for the Perplexity tools that ask a Sonar model (`ask`, `research`).
 * A subclass only picks the model, an optional system steer and the timeout.
 *
 * Concrete subclasses carry their own #[Name], #[Description] and read-only
 * annotations: Laravel MCP reads annotations off the concrete class only.
 */
abstract class PerplexitySonarTool extends PerplexityTool
{
    /** The Sonar model this tool calls (e.g. 'sonar', 'sonar-pro'). */
    abstract protected function model(): string;

    /** Optional system steer sent before the question. Null = none. */
    protected function systemPrompt(): ?string
    {
        return null;
    }

    /** Upstream timeout in seconds. Deeper models (research) take longer. */
    protected function timeout(): int
    {
        return 45;
    }

    public function handle(Request $request, McpCallerContext $caller, PerplexityClient $perplexity): Response|ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'query.required' => 'You must provide a query (what to look up on the web).',
        ]);

        // The customer supplies their own Perplexity key when they connect this
        // connector; McpClient forwards it as a Bearer token.
        if ($caller->connectorApiKey === null) {
            return Response::error(self::MISSING_KEY);
        }

        try {
            $answer = $perplexity->sonar($caller->connectorApiKey, new PerplexitySonarRequestData(
                model: $this->model(),
                query: $validated['query'],
                systemPrompt: $this->systemPrompt(),
                timeout: $this->timeout(),
            ));
        } catch (PerplexityException $e) {
            return Response::error($e->getMessage());
        }

        return $this->answerResponse($answer);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The natural-language question or topic to look up on the live web, e.g. "who won the last European football cup" or "compare the latest iPhone and Pixel cameras".')
                ->required(),
        ];
    }
}
