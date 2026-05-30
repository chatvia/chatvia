<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Data\CreateTemplateData;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Support\Facades\DB;

class CreateTemplateAction
{
    public function execute(CreateTemplateData $data): AgentTemplate
    {
        return DB::transaction(function () use ($data): AgentTemplate {
            /** @var AgentTemplate $template */
            $template = $data->user->templates()->create([
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
