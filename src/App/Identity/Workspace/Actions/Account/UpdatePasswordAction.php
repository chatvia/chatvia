<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Data\Account\UpdatePasswordData;
use Illuminate\Support\Facades\Hash;

/**
 * Saves a new password for the signed-in user.
 */
class UpdatePasswordAction
{
    public function execute(UpdatePasswordData $data): void
    {
        $data->user->update([
            'password' => Hash::make($data->password),
        ]);
    }
}
