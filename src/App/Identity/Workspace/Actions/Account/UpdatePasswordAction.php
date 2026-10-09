<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Data\Account\UpdatePasswordData;
use App\Identity\Workspace\Services\PasswordSetup;
use Illuminate\Support\Facades\Hash;

/**
 * Saves a new password and clears the first-time password setup flag.
 */
class UpdatePasswordAction
{
    public function __construct(
        private readonly PasswordSetup $passwordSetup,
    ) {}

    public function execute(UpdatePasswordData $data): void
    {
        $data->user->update([
            'password' => Hash::make($data->password),
        ]);

        // A saved password fulfils the first-time setup requirement for
        // trial-checkout accounts.
        $this->passwordSetup->clear($data->user);
    }
}
