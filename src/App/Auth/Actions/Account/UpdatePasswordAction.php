<?php

namespace App\Auth\Actions\Account;

use App\Auth\Data\Account\UpdatePasswordData;
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
