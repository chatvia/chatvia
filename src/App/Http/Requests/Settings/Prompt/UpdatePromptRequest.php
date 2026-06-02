<?php

namespace App\Http\Requests\Settings\Prompt;

use App\Workspace\Models\User;
use Closure;
use Domain\Prompt\Data\UpdatePromptData;
use Domain\Prompt\Models\AgentPrompt;
use Illuminate\Foundation\Http\FormRequest;

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
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = $this->user()->prompts()
                        ->where('name', $value)
                        ->where('external_id', '!=', $this->route('external_id'))
                        ->exists();
                    if ($exists) {
                        $fail('A prompt with this name already exists on your account.');
                    }
                },
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

        return $this->prompt = $user->prompts()
            ->where('external_id', $this->route('external_id'))
            ->first();
    }
}
