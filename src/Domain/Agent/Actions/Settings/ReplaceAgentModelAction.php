<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Collection;

/**
 * Moves every agent that answers with one model to another, when an admin
 * removes the model (Settings → AI models). Each agent is saved on its own,
 * so whatever listens to an agent's changes sees it.
 */
final class ReplaceAgentModelAction
{
    /**
     * @return int how many agents moved
     */
    public function execute(Llm $from, Llm $to): int
    {
        $moved = 0;

        Agent::query()
            ->where('llm_id', $from->id)
            ->chunkById(200, function (Collection $agents) use ($to, &$moved): void {
                foreach ($agents as $agent) {
                    /** @var Agent $agent */
                    $agent->forceFill(['llm_id' => $to->id])->save();
                    $moved++;
                }
            });

        return $moved;
    }
}
