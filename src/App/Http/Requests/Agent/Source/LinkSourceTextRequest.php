<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates linking an existing text to an agent. Whether the caller may see
 * the text is checked by the text policy in the controller.
 */
class LinkSourceTextRequest extends FormRequest
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
            'text_id' => ['required', 'exists:source_texts,id'],
        ];
    }
}
