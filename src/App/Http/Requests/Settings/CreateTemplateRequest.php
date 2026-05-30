<?php

namespace App\Http\Requests\Settings;

use App\Auth\Models\User;
use App\Http\Requests\Settings\Concerns\ResolvesTemplateInput;
use Closure;
use Domain\Agents\Data\CreateTemplateData;
use Illuminate\Foundation\Http\FormRequest;

class CreateTemplateRequest extends FormRequest
{
    use ResolvesTemplateInput;

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
                    if ($this->user()->templates()->where('name', $value)->exists()) {
                        $fail('A template with this name already exists on your account.');
                    }
                },
            ],
            ...$this->commonTemplateRules(),
        ];
    }

    public function toData(): CreateTemplateData
    {
        /** @var User $user */
        $user = $this->user();

        return new CreateTemplateData(...['user' => $user, ...$this->templateAttributes()]);
    }
}
