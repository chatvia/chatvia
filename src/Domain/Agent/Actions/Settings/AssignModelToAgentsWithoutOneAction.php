<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Collection;

/**
 * Gives a model to every agent that has none: agents made before any model
 * was added (by the demo data, or while the setup was skipped). The setup
 * calls it with the default model once it has added one. Each agent is saved
 * on its own, so whatever listens to an agent's changes sees it.
 */
final class AssignModelToAgentsWithoutOneAction
{
    /**
     * @return int how many agents got the model
     */
    public function execute(Llm $model): int
    {
        $assigned = 0;

        Agent::query()
            ->whereNull('llm_id')
            ->chunkById(200, function (Collection $agents) use ($model, &$assigned): void {
                foreach ($agents as $agent) {
                    /** @var Agent $agent */
                    $agent->forceFill(['llm_id' => $model->id])->save();
                    $assigned++;
                }
            });

        return $assigned;
    }
}
