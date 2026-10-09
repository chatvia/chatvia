<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Analytics;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Names one emergent topic suggestion (by its label) to promote into a real
 * topic or to dismiss. The permission is checked by the route middleware.
 */
class SuggestedTopicLabelRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:255'],
        ];
    }

    public function label(): string
    {
        return (string) $this->validated('label');
    }
}
