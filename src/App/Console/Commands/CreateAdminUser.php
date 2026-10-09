<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Identity\Workspace\Actions\Account\CreateAdminUserAction;
use App\Identity\Workspace\Data\Account\CreateAdminUserData;
use App\Identity\Workspace\Exceptions\EmailAlreadyTakenException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Creates an admin account from the command line (see
 * {@see CreateAdminUserAction}). The password is asked for when it is not
 * passed, so it stays out of the shell history.
 */
#[Description('Create an admin user')]
#[Signature('admin:create-user {email} {name} {password?}')]
class CreateAdminUser extends Command
{
    public function handle(CreateAdminUserAction $createAdmin): int
    {
        $data = new CreateAdminUserData(
            name: (string) $this->argument('name'),
            email: (string) $this->argument('email'),
            password: (string) ($this->argument('password') ?? $this->secret('Enter password for the admin user')),
        );

        try {
            $admin = $createAdmin->execute($data);
        } catch (EmailAlreadyTakenException) {
            $this->error('User with this email already exists!');

            return self::FAILURE;
        }

        $this->info('Admin user created successfully!');
        $this->info("Email: {$admin->email}");
        $this->info("Name: {$admin->name}");

        return self::SUCCESS;
    }
}
