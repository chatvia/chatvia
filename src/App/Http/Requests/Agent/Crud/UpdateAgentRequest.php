<?php

namespace App\Http\Requests\Agent\Crud;

use App\Http\Rules\AgentSlug;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Account\UpdateAgentData;
use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a partial update of an agent from the dashboard: name, chat slug,
 * and either custom system instructions or a saved prompt (never both). Fields
 * that are absent are left unchanged.
 */
class UpdateAgentRequest extends FormRequest
{
    /**
     * The route already enforces `can.access.agent` (the agent policy) and
     * `agent.permission:agent.setup`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Agent $agent */
        $agent = $this->route('agent');

        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'slug' => ['sometimes', 'nullable', new AgentSlug($agent)],
            'system_instructions' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'prompt_id' => ['sometimes', 'nullable', 'string', 'prohibits:system_instructions'],
        ];
    }

    public function toData(): UpdateAgentData
    {
        /** @var User $actor */
        $actor = $this->user();
        /** @var Agent $agent */
        $agent = $this->route('agent');

        return new UpdateAgentData(
            agent: $agent,
            actor: $actor,
            name: $this->has('name') ? $this->string('name')->toString() : null,
            slug: $this->has('slug') ? $this->input('slug') : null,
            changePrompt: $this->has('prompt_id'),
            promptExternalId: $this->input('prompt_id'),
            changeSystemInstructions: $this->has('system_instructions'),
            systemInstructions: $this->input('system_instructions'),
        );
    }
}
