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
            'retention' => ['sometimes', 'integer'],
        ];
    }
}
