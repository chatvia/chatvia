<?php

declare(strict_types=1);

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Collection;

/**
 * The model an agent answers with: its own, or the default chat model
 * (Settings → AI models) while it has none, as an agent made before any
 * model was added has. Null only when no model can answer at all.
 *
 * An agent may also offer chatters a few models to pick from (Setup →
 * Model), while the admin lets agents do so: a typed turn then answers with
 * the chatter's pick, when it is still offered.
 */
final readonly class AgentModel
{
    public function __construct(
        private TaskModels $models,
    ) {}

    public function for(Agent $agent): ?Llm
    {
        return $agent->llm ?? $this->models->find(LlmTask::Chat);
    }

    /**
     * The model a typed turn answers with: the one the chatter picked when
     * the agent offers it, else the agent's own. A pick that is no longer
     * offered (a remembered one, or an old widget's) falls back quietly.
     */
    public function forTurn(Agent $agent, ?int $pickedId): ?Llm
    {
        $picked = $pickedId !== null ? $this->offered($agent)->firstWhere('id', $pickedId) : null;

        return $picked ?? $this->for($agent);
    }

    /**
     * The models chatters may pick from: the agent's own first, then the
     * ones it offers that can still answer. Empty while the admin or the
     * agent has the choice off, or when fewer than two models are left, as
     * one model is no choice.
     *
     * @return Collection<int, Llm>
     */
    public function offered(Agent $agent): Collection
    {
        $choice = $agent->settings('model_choice');

        if (! config('chatvia.ai.chatter_model_choice') || ! $choice->get('enabled')) {
            return collect();
        }

        // From the cached catalog (its provider loaded), not the agent's
        // relation, so page props need no extra query or eager load.
        $own = Llm::catalog()->firstWhere('id', $agent->llm_id) ?? $this->models->find(LlmTask::Chat);
        $ids = collect((array) $choice->get('models', []))->map(fn (mixed $id): int => (int) $id);
        $offered = Llm::available()
            ->filter(fn (Llm $llm): bool => $ids->contains($llm->id) && $llm->id !== $own?->id)
            ->sortBy(fn (Llm $llm): int|false => $ids->search($llm->id))
            ->values();

        $models = $own instanceof Llm ? $offered->prepend($own) : $offered;

        return $models->count() > 1 ? $models->values() : collect();
    }
}
