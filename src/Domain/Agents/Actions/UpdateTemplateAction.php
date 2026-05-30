<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use Domain\Agents\Data\UpdateTemplateData;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Support\Facades\DB;

class UpdateTemplateAction
{
    public function execute(User $user, string $externalId, UpdateTemplateData $data): AgentTemplate
    {
        return DB::transaction(function () use ($user, $externalId, $data): AgentTemplate {
            /** @var AgentTemplate $template */
            $template = $user->templates()->where('external_id', $externalId)->firstOrFail();

            $template->update([
                'name' => $data->name,
                'agent_prompt_id' => $data->agentPromptId,
                'is_public' => $data->isPublic,
                'hosted_chat_config' => $data->hostedChatConfig,
                'widget_config' => $data->widgetConfig,
                'bar_config' => $data->barConfig,
            ]);

            return $template;
        });
    }
}
