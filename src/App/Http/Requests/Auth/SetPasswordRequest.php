<?php

namespace App\Http\Requests\Auth;

use App\Identity\Workspace\Data\Account\UpdatePasswordData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        // Only accounts still flagged for first-time setup may post here —
        // for anyone else this would be a password change that skips the
        // current-password check.
        return $user !== null && app(PasswordSetup::class)->isRequired($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
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
