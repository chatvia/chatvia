<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use Domain\Agents\Data\Resources\AgentTemplatePickerData;
use Domain\Agents\Data\Resources\AgentTemplatePickerListData;
use Domain\Agents\Models\AgentTemplate;
use Spatie\LaravelData\DataCollection;

/**
 * List the user's templates trimmed to the picker shape (id / name / type),
 * newest first.
 */
class ListTemplatesForPickerAction
{
    public function execute(User $user): AgentTemplatePickerListData
    {
        $templates = $user->templates()
            ->latest()
            ->get()
            ->map(fn (AgentTemplate $template): AgentTemplatePickerData => AgentTemplatePickerData::fromModel($template));

        return new AgentTemplatePickerListData(
            data: new DataCollection(AgentTemplatePickerData::class, $templates),
        );
    }
}
