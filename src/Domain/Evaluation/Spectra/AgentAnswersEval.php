<?php

namespace Domain\Evaluation\Spectra;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Evaluation\Services\EvalAgentRunner;
use Illuminate\Support\Str;
use RuntimeException;
use Spectra\Evaluations\Evaluation;
use Spectra\Evaluations\Scorers\Contains;
use Spectra\Models\SpectraDatasetExample;
use Spectra\Models\SpectraRequest;

/**
 * Runs the `agent-answers` dataset in /spectra through an agent's real
 * pipeline ({@see EvalAgentRunner}: tools recorded, never called), so a
 * change to the instructions or the model can be compared run by run on the
 * Evals screen:
 *
 *   php artisan spectra:eval 'Domain\Evaluation\Spectra\AgentAnswersEval'
 *
 * An example's input is `['agent' => external id, 'prompt' => message]`. An
 * example added from a chat call on the dashboard holds only the prompt; it
 * runs on the agent of the conversation that call belongs to.
 */
class AgentAnswersEval extends Evaluation
{
    public function __construct(private readonly EvalAgentRunner $runner) {}

    public function dataset(): string
    {
        return 'agent-answers';
    }

    /** @param  array{agent?: string, prompt: string}  $input */
    public function run(mixed $input, SpectraDatasetExample $example): string
    {
        $result = $this->runner->run($this->agentFor($input, $example), $input['prompt']);

        throw_if(filled($result->error), RuntimeException::class, (string) $result->error);

        return $result->answer;
    }

    public function scorers(): array
    {
        return [
            'answered' => fn (string $output): bool => filled($output),
            'mentions_expected' => new Contains,
            'concise' => fn (string $output): bool => Str::length($output) <= 800,
        ];
    }

    /** @param  array{agent?: string, prompt: string}  $input */
    private function agentFor(array $input, SpectraDatasetExample $example): Agent
    {
        if (filled($input['agent'] ?? null)) {
            return Agent::query()->where('external_id', $input['agent'])->firstOrFail();
        }

        $conversation = SpectraRequest::query()->find($example->source_request_id)?->trackable;

        throw_unless($conversation instanceof Conversation, RuntimeException::class, 'The example names no agent, and its call has no conversation.');

        return $conversation->agent;
    }
}
