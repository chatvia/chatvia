<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Data\Account\CreateAdminUserData;
use App\Identity\Workspace\Exceptions\EmailAlreadyTakenException;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Support\Enums\User\UserType;

/**
 * Creates a platform admin: access to the admin panel plus a normal workspace
 * with its default team, so the account can build agents straight away. On a
 * self-hosted install this is the usual way to create the first account. The
 * email is verified, since the operator typed it on the server.
 */
class CreateAdminUserAction
{
    public function __construct(
        private readonly TeamService $teams,
    ) {}

    /**
     * @throws EmailAlreadyTakenException
     */
    public function execute(CreateAdminUserData $data): User
    {
        if (User::query()->where('email', $data->email)->exists()) {
            throw EmailAlreadyTakenException::for($data->email);
        }

        return DB::transaction(function () use ($data): User {
            /** @var User $admin */
            $admin = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'email_verified_at' => now(),
                'type' => UserType::Admin,
            ]);

            $this->teams->createDefaultTeam($admin);
            $admin->bootstrapCurrentTeam();

            return $admin;
        });
    }
}
