<?php

namespace App\Http\Requests\Auth;

use App\Http\Auth\BotCheck;
use App\Http\Auth\BotCheckForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

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
     * Staff emails are stored lowercase. The typed address is lowered
     * before the rules run, so the reset broker's own `email = ?` lookup
     * finds the account.
     */
    protected function prepareForValidation(): void
    {
        if (filled($this->input('email')) && ! is_array($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
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
