<?php

use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\Support\Http\BufferedSseResponder;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * Reasoning in the hosted chat, end to end: the agent's effort is sent as a
 * value the model takes (its least while the admin does not let agents
 * reason, nothing to a model whose values are not known), and
 * the model's thinking streams as `reasoning` events before the answer,
 * never as part of it, and never while answers are held for the guardrails.
 */
beforeEach(function (): void {
    connectAiProviders();
    $this->model = Llm::factory()->create(['capabilities' => modelsDevEntry(['none', 'low', 'medium', 'high'])]);
    $this->agent = Agent::factory()->create(['is_public' => true, 'llm_id' => $this->model->id]);
    $this->agent->setSetting('effort', 'high', 'reasoning');
    config(['chatvia.ai.reasoning.enabled' => true]);

    $this->llm = new ScriptedLlmProvider(ScriptedLlmProvider::body(
        ['id' => 'gen-1', 'choices' => [['delta' => ['reasoning_content' => 'Thinking it over.']]]],
        ['choices' => [['delta' => ['content' => 'The answer.']]]],
    ));
    bindFakeLlmProvider($this->llm);
    app()->instance(SseResponder::class, new BufferedSseResponder);
});

/** @return Collection<int, array<string, mixed>> the SSE events of one turn */
function srtTurn(Agent $agent): Collection
{
    $response = test()->post("/chat/{$agent->external_id}/stream/".Str::uuid7(), ['message' => 'Hi'], ['Accept' => 'text/event-stream'])->assertOk();

    return collect(BufferedSseResponder::events($response->streamedContent()));
}

it('sends the agent\'s effort and streams the thinking before the answer', function (): void {
    $events = srtTurn($this->agent);
    $types = $events->pluck('type')->all();

    expect($this->llm->sentSettings[0]['reasoning_effort'] ?? null)->toBe('high')
        ->and($this->llm->sentSettings[0]['show_reasoning'] ?? null)->toBeTrue()
        ->and($events->where('type', 'reasoning')->pluck('data.text')->implode(''))->toBe('Thinking it over.')
        ->and($events->where('type', 'delta')->pluck('data.text')->implode(''))->toBe('The answer.')
        ->and(array_search('reasoning', $types, true))->toBeLessThan(array_search('delta', $types, true));
});

it('sends the least thinking and shows none while the admin does not let agents reason', function (): void {
    config(['chatvia.ai.reasoning.enabled' => false]);

    $events = srtTurn($this->agent);

    expect($this->llm->sentSettings[0]['reasoning_effort'] ?? null)->toBe('none')
        ->and($this->llm->sentSettings[0])->not->toHaveKey('show_reasoning')
        ->and($events->where('type', 'reasoning'))->toBeEmpty()
        ->and($events->where('type', 'delta')->pluck('data.text')->implode(''))->toBe('The answer.');
});

it('sends no effort to a model whose effort values are not known', function (): void {
    $this->model->update(['capabilities' => null]);

    $events = srtTurn($this->agent->refresh());

    expect($this->llm->sentSettings[0])->not->toHaveKey('reasoning_effort')
        ->and($events->where('type', 'reasoning'))->toBeEmpty();
});

it('shows no thinking while answers are held for the guardrails', function (): void {
    $this->agent->setSetting('check_before_showing', true, 'guardrails');

    $events = srtTurn($this->agent);

    expect($this->llm->sentSettings[0]['reasoning_effort'] ?? null)->toBe('high')
        ->and($this->llm->sentSettings[0])->not->toHaveKey('show_reasoning')
        ->and($events->where('type', 'reasoning'))->toBeEmpty();
});
