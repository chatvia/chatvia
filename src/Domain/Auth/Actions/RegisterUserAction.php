<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;
use Domain\Analytics\Services\IP2Country;
use Domain\Auth\Data\RegisterUserData;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
            ]);

            event(new Registered($user));

            $user->ensureCurrentTeam();

            return $user;
        });
    }
}
