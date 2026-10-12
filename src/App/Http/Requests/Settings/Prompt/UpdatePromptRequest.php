<?php

namespace App\Http\Requests\Settings\Prompt;

use App\Http\Rules\WorkspaceRule;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Data\UpdatePromptData;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an edit of a saved prompt in the workspace Library. A prompt that
 * is not the caller's workspace's answers 404, as if it did not exist; the
 * name stays unique within the workspace.
 */
class UpdatePromptRequest extends FormRequest
{
    private ?AgentPrompt $prompt = null;

    public function authorize(): bool
    {
        abort_unless($this->prompt() instanceof AgentPrompt, 404);

        return true;
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
                WorkspaceRule::unique('agent_prompts', $this->user()->workspace(), 'name')
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
