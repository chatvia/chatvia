<?php

namespace App\Http\Requests\Settings\Prompt;

use App\Http\Rules\WorkspaceRule;
use Domain\Agent\Prompt\Data\UpdatePromptData;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an edit of a saved prompt in the workspace Library. The route
 * binds `{prompt}` and `workspace.scope` 404s another workspace's, as if it
 * did not exist; the name stays unique within the workspace.
 */
class UpdatePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
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
                    ->ignore($this->prompt()->external_id, 'external_id'),
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

    private function prompt(): AgentPrompt
    {
        $prompt = $this->route('prompt');
        abort_unless($prompt instanceof AgentPrompt, 404);

        return $prompt;
    }
}
