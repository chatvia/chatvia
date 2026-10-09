<?php

namespace App\Mcp\Tools\Perplexity;

use Domain\Integration\Builtin\Data\Perplexity\PerplexityAnswerData;
use Domain\Integration\Builtin\Data\Perplexity\PerplexitySandboxExecutionData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

/**
 * The `run_code` tool: a task run in Perplexity's remote code sandbox, with
 * the program output returned next to the answer.
 */
#[Name('run_code')]
#[Description('Run code in an isolated sandbox to compute, analyze, or transform data — calculations, parsing, simulations. Describe the task (optionally with code to run) and get the program output plus an explanation. Nothing on your side is touched; the sandbox is remote and throwaway.')]
#[IsOpenWorld]
class PerplexityRunCodeTool extends PerplexityAgentTool
{
    protected function toolType(): string
    {
        return 'sandbox';
    }

    protected function instructions(): ?string
    {
        return 'Use the sandbox to write and execute the code the task needs, then report the results plainly, including relevant output values.';
    }

    /**
     * Sandbox sessions are containers, not single calls — give a slow run
     * room without hitting the MCP client's own 5-minute ceiling.
     */
    protected function timeout(): int
    {
        return 240;
    }

    protected function rules(): array
    {
        return [
            'task' => ['required', 'string', 'min:2', 'max:4000'],
        ];
    }

    protected function validationMessages(): array
    {
        return [
            'task.required' => 'You must describe the task to compute (optionally including the code to run).',
        ];
    }

    protected function inputFor(array $validated): string
    {
        return (string) $validated['task'];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task' => $schema->string()
                ->description('What to compute or analyze, e.g. "calculate the monthly payment on a €300k loan at 3.8% over 25 years". May include code to execute verbatim.')
                ->required(),
        ];
    }

    protected function extraStructured(PerplexityAnswerData $answer): array
    {
        $sandbox = $answer->sandbox;

        if ($sandbox === null) {
            return [];
        }

        return [
            'language' => $sandbox->language,
            'code' => $sandbox->code,
            'status' => $sandbox->status,
            'executions' => array_map(static fn (PerplexitySandboxExecutionData $execution): array => [
                'stdout' => $execution->stdout,
                'stderr' => $execution->stderr,
                'exit_code' => $execution->exitCode,
            ], $sandbox->executions),
        ];
    }
}
