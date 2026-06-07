<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Data\Account\RegisterUserData;
use App\Identity\Workspace\Models\User;
use Domain\Analytics\Services\IP2Country;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Support\Enums\User\UserType;

class RegisterUserAction
{
    public function execute(RegisterUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'domain' => $data->domain,
                'registered_ip' => $data->registered_ip,
                'registered_country_code' => IP2Country::getCountryByIP($data->registered_ip),
                // Set explicitly so UserObserver::created — which auto-provisions
                // billing, quota, and the default team — sees the right type
                // instead of a null read-from-DB-default placeholder.
                'type' => UserType::Workspace,
            ]);

            event(new Registered($user));

            $user->bootstrapCurrentTeam();

            return $user;
        });
    }
}
