<?php

namespace App\Http\Requests\Settings\Prompt;

use App\Http\Rules\WorkspaceRule;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Data\CreatePromptData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new saved prompt in the workspace Library. The name must be
 * unique within the workspace, so the check is keyed to the workspace, not
 * the teammate who is typing. Access is gated by the `account.prompts`
 * route permission.
 */
class CreatePromptRequest extends FormRequest
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
                WorkspaceRule::unique('agent_prompts', $this->user()->workspace(), 'name'),
            ],
            'prompt' => ['required', 'string', 'max:10000'],
        ];
    }

    public function toData(): CreatePromptData
    {
        /** @var User $user */
        $user = $this->user();

        return new CreatePromptData(
            user: $user,
            name: $this->string('name')->toString(),
            prompt: $this->string('prompt')->toString(),
        );
    }
}
