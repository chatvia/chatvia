<?php

namespace App\Http\Requests\Agents\Settings;

use Illuminate\Foundation\Http\FormRequest;

class MessageHistorySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'retention' => ['sometimes', 'integer'],
            'context_window_enabled' => ['sometimes', 'boolean'],
            'context_window_size' => ['sometimes', 'integer'],
        ];
    }
}
