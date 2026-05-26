<?php

namespace App\Http\Requests\Auth;

use Domain\Auth\Data\ResetPasswordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class ResetPasswordRequest extends FormRequest
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
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function toData(): ResetPasswordData
    {
        return new ResetPasswordData(
            token: $this->string('token')->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            password_confirmation: $this->string('password_confirmation')->toString(),
        );
    }
}
