<?php

namespace App\Http\Requests\Settings;

use App\Auth\Models\User;
use Domain\Auth\Data\UpdatePasswordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];
    }

    public function toData(): UpdatePasswordData
    {
        /** @var User $user */
        $user = $this->user();

        return new UpdatePasswordData(
            user: $user,
            password: $this->string('password')->toString(),
        );
    }
}
