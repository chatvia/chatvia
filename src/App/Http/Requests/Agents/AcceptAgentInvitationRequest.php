<?php

namespace App\Http\Requests\Agents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptAgentInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
