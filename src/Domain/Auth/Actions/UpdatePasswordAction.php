<?php

namespace Domain\Auth\Actions;

use Domain\Auth\Data\UpdatePasswordData;
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
