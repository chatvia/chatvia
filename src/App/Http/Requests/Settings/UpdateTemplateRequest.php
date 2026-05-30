<?php

namespace App\Http\Requests\Settings;

use App\Auth\Models\User;
use App\Http\Requests\Settings\Concerns\ResolvesTemplateInput;
use Closure;
use Domain\Agents\Data\UpdateTemplateData;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateRequest extends FormRequest
{
    use ResolvesTemplateInput;

    private ?AgentTemplate $template = null;

    public function authorize(): bool
    {
        return $this->template() !== null;
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
                    $exists = $this->user()->templates()
                        ->where('name', $value)
                        ->where('external_id', '!=', $this->route('external_id'))
                        ->exists();
                    if ($exists) {
                        $fail('A template with this name already exists on your account.');
                    }
                },
            ],
            ...$this->commonTemplateRules(),
        ];
    }

    public function toData(): UpdateTemplateData
    {
        return new UpdateTemplateData(...$this->templateAttributes());
    }

    private function template(): ?AgentTemplate
    {
        if ($this->template !== null) {
            return $this->template;
        }

        /** @var User $user */
        $user = $this->user();

        return $this->template = $user->templates()
            ->where('external_id', $this->route('external_id'))
            ->first();
    }
}
