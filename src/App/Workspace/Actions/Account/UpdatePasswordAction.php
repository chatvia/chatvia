<?php

namespace App\Workspace\Actions\Account;

use App\Workspace\Data\Account\UpdatePasswordData;
use Illuminate\Support\Facades\Hash;

class UpdatePasswordAction
{
    public function execute(UpdatePasswordData $data): void
    {
        $data->user->update([
            'password' => Hash::make($data->password),
        ]);
    }
}
