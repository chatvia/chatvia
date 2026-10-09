<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Perplexity;

use App\Mcp\McpCallerContext;
use Domain\Integration\Builtin\Data\Perplexity\PerplexityAgentRequestData;
use Domain\Integration\Builtin\Data\Perplexity\PerplexityAnswerData;
use Domain\Integration\Builtin\Exceptions\PerplexityException;
use Domain\Integration\Builtin\Services\PerplexityClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Base for the Perplexity tools backed by one Agent API tool (web_search,
 * fetch_url, finance_search, people_search, sandbox). A subclass picks the
 * tool type and, when its input is not a plain `query`, its own rules,
 * schema and input framing.
 *
 * Concrete subclasses carry their own #[Name], #[Description] and read-only
 * annotations: Laravel MCP reads annotations off the concrete class only.
 */
abstract class PerplexityAgentTool extends PerplexityTool
{
    /** The Agent API tool type this MCP tool enables (e.g. 'web_search'). */
    abstract protected function toolType(): string;

    /** Optional steer passed as the Agent API `instructions` field. */
    protected function instructions(): ?string
    {
        return null;
    }

    /** Upstream timeout in seconds. Sandbox runs need far longer than search. */
    protected function timeout(): int
    {
        return 60;
    }

    /**
     * @return array<string, list<string>>
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
     * Tool-specific structured fields to add to the result (e.g. what the
     * sandbox ran). None by default.
     *
     * @return array<string, mixed>
     */
    protected function extraStructured(PerplexityAnswerData $answer): array
    {
        return [];
    }

    public function handle(Request $request, McpCallerContext $caller, PerplexityClient $perplexity): Response|ResponseFactory
    {
        $validated = $request->validate($this->rules(), $this->validationMessages());

        // The customer supplies their own Perplexity key when they connect this
        // connector; McpClient forwards it as a Bearer token.
        if ($caller->connectorApiKey === null) {
            return Response::error(self::MISSING_KEY);
        }

        try {
            $answer = $perplexity->agent($caller->connectorApiKey, new PerplexityAgentRequestData(
                toolType: $this->toolType(),
                input: $this->inputFor($validated),
                instructions: $this->instructions(),
                timeout: $this->timeout(),
            ));
        } catch (PerplexityException $e) {
            return Response::error($e->getMessage());
        }

        return $this->answerResponse($answer, $this->extraStructured($answer));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The natural-language question or topic to look up, e.g. "who won the last European football cup".')
                ->required(),
        ];
    }
}
