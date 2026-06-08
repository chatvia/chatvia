<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;

class SetAgentStartersAction
{
    /**
     * Replace an agent's conversation starters with the given ordered list.
     * Each entry is normalized to `{ text, icon }`, where `icon` is an optional
     * Heroicons (outline) export name or null.
     *
     * @param  array<int, array{text: string, icon?: string|null}>  $starters
     */
    public function execute(Agent $agent, array $starters): void
    {
        $normalized = array_values(array_map(
            fn (array $starter): array => [
                'text' => $starter['text'],
                'icon' => $starter['icon'] ?? null,
            ],
            $starters,
        ));

        $agent->starterSet()->updateOrCreate([], ['starters' => $normalized]);
    }
}
