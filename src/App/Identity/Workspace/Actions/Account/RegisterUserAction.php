<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Data\Account\RegisterUserData;
use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Support\Enums\User\UserType;

/**
 * Create a new workspace owner from the sign-up form.
 *
 * Only the user row is created here. The default team, billing row and quota
 * are provisioned later, when the trial checkout's Stripe webhook arrives.
 * `Registered` (which sends the verification email) is dispatched after the
 * transaction commits, so a listener never sees a user that was rolled back.
 */
class RegisterUserAction
{
    public function execute(RegisterUserData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'domain' => $data->domain,
                'registered_ip' => $data->registered_ip,
                'registered_country_code' => $data->registered_country_code,
                'type' => UserType::Workspace,
            ]);

            $user->bootstrapCurrentTeam();

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
