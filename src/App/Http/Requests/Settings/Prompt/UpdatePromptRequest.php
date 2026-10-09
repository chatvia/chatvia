<?php

namespace App\Http\Requests\Settings\Prompt;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Data\UpdatePromptData;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an edit of a saved prompt in the workspace Library. Authorization
 * fails (403) unless the prompt in the route belongs to the caller's workspace;
 * the name stays unique within the workspace.
 */
class UpdatePromptRequest extends FormRequest
{
    private ?AgentPrompt $prompt = null;

    public function authorize(): bool
    {
        return $this->prompt() instanceof AgentPrompt;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('agent_prompts', 'name')
                    ->where('user_id', $this->user()->workspace()->id)
                    ->ignore($this->route('external_id'), 'external_id'),
            ],
            'prompt' => ['required', 'string', 'max:10000'],
        ];
    }

    public function toData(): UpdatePromptData
    {
        return new UpdatePromptData(
            name: $this->string('name')->toString(),
            prompt: $this->string('prompt')->toString(),
        );
    }

    private function prompt(): ?AgentPrompt
    {
        if ($this->prompt instanceof AgentPrompt) {
            return $this->prompt;
        }

        /** @var User $user */
        $user = $this->user();

        return $this->prompt = $user->workspace()->prompts()
            ->where('external_id', $this->route('external_id'))
            ->first();
    }
}
