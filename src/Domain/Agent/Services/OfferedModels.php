<?php

declare(strict_types=1);

namespace Domain\Agent\Services;

use Domain\Agent\Data\ModelChoice\OfferedModelData;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\ModelsDevCatalog;

/**
 * The models an agent offers chatters ({@see AgentModel::offered()}), as the
 * hosted chat's and the widget's pickers show them: the models.dev name (the
 * provider's id when it is not listed) and an absolute logo URL, so a widget
 * on another site can load it. Empty means no picker.
 */
final readonly class OfferedModels
{
    public function __construct(
        private AgentModel $models,
        private ModelsDevCatalog $catalog,
    ) {}

    /**
     * @return list<OfferedModelData>
     */
    public function for(Agent $agent): array
    {
        $offered = $this->models->offered($agent);
        $default = $offered->first()?->id;

        return $offered->map(fn (Llm $llm): OfferedModelData => new OfferedModelData(
            id: $llm->id,
            name: $this->catalog->displayName($llm->provider->kind, $llm->name) ?? $llm->name,
            logo: $this->logo($llm),
            is_default: $llm->id === $default,
        ))->values()->all();
    }

    private function logo(Llm $llm): ?string
    {
        $logo = $this->catalog->logo($llm->provider->kind, $llm->name);

        return $logo !== null ? route('model-logo', ['logo' => $logo]) : null;
    }
}
