<?php

namespace App\Http\Requests\Auth;

use App\Identity\Workspace\Data\Account\RegisterUserData;
use App\Identity\Workspace\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

/**
 * The self-serve sign-up form (open only when `billing.registration_enabled`
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function toData(): RegisterUserData
    {
        return new RegisterUserData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            domain: $this->host(),
            registered_ip: $this->ip() ?? '',
            registered_country_code: $this->country(),
        );
    }
}
