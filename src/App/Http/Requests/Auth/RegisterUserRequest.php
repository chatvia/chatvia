<?php

namespace App\Http\Requests\Auth;

use App\Http\Auth\BotCheck;
use App\Http\Auth\BotCheckForm;
use App\Identity\Workspace\Data\Account\RegisterUserData;
use App\Identity\Workspace\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

/**
 * The self-serve sign-up form (open only when `chatvia.registration_enabled`
 * is on). There are no legal checkboxes: a self-hosted install has no terms
 * of its own to accept.
 */
class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Staff emails are stored lowercase. The typed address is lowered
     * before the rules run, so the unique check refuses an address that
     * differs only in case.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ...app(BotCheck::class)->rules(BotCheckForm::SignUp),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(BotCheck::class)->messages();
    }

    public function toData(): RegisterUserData
    {
        return new RegisterUserData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            registered_ip: $this->ip() ?? '',
            registered_country_code: $this->country(),
        );
    }
}
