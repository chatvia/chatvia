<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use Domain\Agents\Data\Resources\AgentTemplateRowData;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Support\Collection;

/**
 * List the user's templates for the Settings → Templates page (each row
 * carries the count of agents currently attached to the template).
 */
class ListTemplatesForManagementPageAction
{
    /**
     * @return Collection<int, AgentTemplateRowData>
     */
    public function execute(User $user): Collection
    {
        return $user->templates()
            ->withCount('agents')
            ->latest()
            ->get()
            ->map(fn (AgentTemplate $template): AgentTemplateRowData => AgentTemplateRowData::fromModel($template));
    }
}
