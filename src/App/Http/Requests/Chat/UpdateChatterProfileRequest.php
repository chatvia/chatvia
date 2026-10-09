<?php

namespace App\Http\Requests\Chat;

use App\Identity\Chatter\Data\UpdateChatterProfileData;
use App\Identity\Workspace\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a signed-in chatter's profile changes on the hosted chat.
 */
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
            // Changing the password requires proving the current one (validated
            // against the authenticated web guard), matching the workspace flow.
            'current_password' => ['required_with:password', 'current_password:web'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
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
            photo: $this->file('photo'),
        );
    }
}
