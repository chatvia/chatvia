<?php

namespace App\Http\Requests\Settings\Prompts;

use App\Auth\Models\User;
use Closure;
use Domain\Agents\Data\Prompts\CreatePromptData;
use Illuminate\Foundation\Http\FormRequest;

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
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->user()->prompts()->where('name', $value)->exists()) {
                        $fail('A prompt with this name already exists on your account.');
                    }
                },
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
