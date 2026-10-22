<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Data\Account\CreateAdminUserData;
use App\Identity\Workspace\Exceptions\EmailAlreadyTakenException;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a platform admin: access to the admin panel plus a normal workspace
 * with its default team, so the account can build agents straight away. On a
 * self-hosted install this is the usual way to create the first account. The
 * email is verified, since the operator typed it on the server.
 */
class CreateAdminUserAction
{
    public function __construct(
        private readonly CreateWorkspaceAction $createWorkspace,
    ) {}

    /**
     * @throws EmailAlreadyTakenException
     */
    public function execute(CreateAdminUserData $data): User
    {
        // Stored lowercase (the User model lowers it), so this is a plain index match.
        if (User::query()->where('email', Str::lower($data->email))->exists()) {
            throw EmailAlreadyTakenException::for($data->email);
        }

        return DB::transaction(function () use ($data): User {
            /** @var User $admin */
            $admin = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'email_verified_at' => now(),
                'is_admin' => true,
            ]);

            $this->createWorkspace->execute($admin);

            return $admin;
        });
    }
}
