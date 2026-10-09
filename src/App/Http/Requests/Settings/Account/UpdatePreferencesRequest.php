<?php

namespace App\Http\Requests\Settings\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Support\Enums\User\DateFormat;

/**
 * The preferences form: date format and, optionally, language.
 */
class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_format' => ['required', Rule::enum(DateFormat::class)],
            'locale' => ['nullable', 'string', Rule::in(array_keys(config('chatvia.languages')))],
        ];
    }
}
