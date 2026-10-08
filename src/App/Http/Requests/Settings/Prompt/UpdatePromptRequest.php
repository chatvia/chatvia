<?php

namespace App\Http\Requests\Settings\Prompt;

use App\Identity\Workspace\Models\User;
use Domain\Prompt\Data\UpdatePromptData;
use Domain\Prompt\Models\AgentPrompt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromptRequest extends FormRequest
{
    private ?AgentPrompt $prompt = null;

    public function authorize(): bool
    {
        return $this->prompt() !== null;
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
        if ($this->prompt !== null) {
            return $this->prompt;
        }

        /** @var User $user */
        $user = $this->user();

        return $this->prompt = $user->workspace()->prompts()
            ->where('external_id', $this->route('external_id'))
            ->first();
    }
}
