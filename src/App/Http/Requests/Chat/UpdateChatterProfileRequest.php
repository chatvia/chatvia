<?php

namespace App\Http\Requests\Chat;

use App\Identity\Chatter\Data\UpdateChatterProfileData;
use App\Identity\Chatter\Models\Chatter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a signed-in chatter's profile changes on the hosted chat.
 */
class UpdateChatterProfileRequest extends FormRequest
{
    /**
     * Only a chatter edits a profile here; staff edit theirs on the dashboard.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof Chatter;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            // Changing the password requires proving the current one, checked
            // against the hosted chat's own `chat` guard: on /chat requests the
            // dashboard's `web` guard has no signed-in user.
            'current_password' => ['required_with:password', 'current_password:chat'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ];
    }

    public function toData(): UpdateChatterProfileData
    {
        /** @var Chatter $user */
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
