<?php

namespace App\Http\Requests\Auth;

use App\Http\Auth\BotCheck;
use App\Http\Auth\BotCheckForm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The "forgot password" form: one email address.
 */
class ForgotPasswordRequest extends FormRequest
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
            'email' => ['required', 'email'],
            ...app(BotCheck::class)->rules(BotCheckForm::PasswordReset),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(BotCheck::class)->messages();
    }

    public function emailAddress(): string
    {
        return $this->string('email')->toString();
    }
}
