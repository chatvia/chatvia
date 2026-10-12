<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Identity\Workspace\Actions\Account\CreateAdminUserAction;
use App\Identity\Workspace\Data\Account\CreateAdminUserData;
use App\Identity\Workspace\Exceptions\EmailAlreadyTakenException;
use App\Identity\Workspace\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Creates an admin account from the command line (see
 * {@see CreateAdminUserAction}). The password is asked for when it is not
 * passed, so it stays out of the shell history; `--stdin` reads it from
 * standard input, so a script (the Docker install's `manage`) can pass it
 * without it showing in the process list. `--if-missing` makes a taken email a
 * success. `--if-no-admin` only creates the first admin of an install, so the
 * Docker image can run it on every start: an admin who changed their email, or
 * a restored backup, gets no second account.
 */
#[Description('Create an admin user')]
#[Signature('admin:create-user {email} {name} {password?} {--stdin : Read the password from standard input} {--if-missing : Succeed without changes when the email is already taken} {--if-no-admin : Succeed without changes when an admin already exists}')]
class CreateAdminUser extends Command
{
    public function handle(CreateAdminUserAction $createAdmin): int
    {
        if ($this->option('if-no-admin') && User::query()->where('is_admin', true)->exists()) {
            $this->info('An admin account already exists. Nothing to do.');

            return self::SUCCESS;
        }

        $data = new CreateAdminUserData(
            name: (string) $this->argument('name'),
            email: (string) $this->argument('email'),
            password: $this->password(),
        );

        if ($data->password === '') {
            $this->error('The password is empty.');

            return self::FAILURE;
        }

        try {
            $admin = $createAdmin->execute($data);
        } catch (EmailAlreadyTakenException) {
            if ($this->option('if-missing')) {
                $this->info("A user with the email {$data->email} already exists. Nothing to do.");

                return self::SUCCESS;
            }

            $this->error('User with this email already exists!');

            return self::FAILURE;
        }

        $this->info('Admin user created successfully!');
        $this->info("Email: {$admin->email}");
        $this->info("Name: {$admin->name}");

        return self::SUCCESS;
    }

    private function password(): string
    {
        if ($this->option('stdin')) {
            $line = fgets(STDIN);

            return $line === false ? '' : rtrim($line, "\r\n");
        }

        return (string) ($this->argument('password') ?? $this->secret('Enter password for the admin user'));
    }
}
