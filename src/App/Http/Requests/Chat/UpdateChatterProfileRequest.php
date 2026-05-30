<?php

namespace App\Http\Requests\Chat;

use App\Auth\Models\User;
use Domain\Auth\Data\UpdateChatterProfileData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChatterProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'current_password' => ['nullable', 'string', 'min:8'],
            'password' => ['nullable', 'required_with:current_password', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function toData(): UpdateChatterProfileData
    {
        /** @var User $user */
        $user = $this->user();

        return new UpdateChatterProfileData(
            user: $user,
            name: $this->input('name'),
            currentPassword: $this->input('current_password'),
            newPassword: $this->input('password'),
        );
    }
}
