<?php

namespace App\Http\Requests\Agent\Source;

use Illuminate\Foundation\Http\FormRequest;

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
