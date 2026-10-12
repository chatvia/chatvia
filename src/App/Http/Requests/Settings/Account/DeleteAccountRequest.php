<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Step 1 of account deletion. Only a workspace owner may delete an account, and
 * they must re-enter their password before we email the signed confirmation
 * link. The actual deletion (step 2) is gated separately by that link.
 */
class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->isWorkspaceOwner();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'current_password'],
        ];
    }
}
