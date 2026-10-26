<?php

declare(strict_types=1);

namespace App\Console\Commands\Admin;

use App\Identity\Workspace\Actions\Account\CreateAdminUserAction;
use App\Identity\Workspace\Data\Account\CreateAdminUserData;
use App\Identity\Workspace\Exceptions\EmailAlreadyTakenException;
use App\Identity\Workspace\Exceptions\WorkspaceLimitReachedException;
use App\Identity\Workspace\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

/**
 * Creates an admin account from the command line (see
 * {@see CreateAdminUserAction}). Run with no arguments, it asks for the
 * email and name in the terminal. The password is asked for when it is not
 * passed, so it stays out of the shell history; `--stdin` reads it from
 * standard input, so a script (the Docker install's `manage`) can pass it
 * without it showing in the process list. `--if-missing` makes a taken email a
 * success. `--if-no-admin` only creates the first admin of an install, so the
 * Docker image can run it on every start: an admin who changed their email, or
 * a restored backup, gets no second account.
 */
#[Description('Create an admin user')]
#[Signature('chatvia:admin:create-user {email : Email address of the admin} {name : Name of the admin} {password? : The password (asked for when left out)} {--stdin : Read the password from standard input} {--if-missing : Succeed without changes when the email is already taken} {--if-no-admin : Succeed without changes when an admin already exists}')]
class CreateAdminUser extends Command implements PromptsForMissingInput
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
        } catch (WorkspaceLimitReachedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Admin user created successfully!');
        $this->info("Email: {$admin->email}");
        $this->info("Name: {$admin->name}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, \Closure(): string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'email' => fn (): string => text(
                label: 'Email address of the admin user',
                placeholder: 'admin@example.com',
                validate: ['email' => 'required|email'],
            ),
            'name' => fn (): string => text(
                label: 'Name of the admin user',
                required: true,
            ),
        ];
    }

    private function password(): string
    {
        if ($this->option('stdin')) {
            $line = fgets(STDIN);

            return $line === false ? '' : Str::rtrim($line, "\r\n");
        }

        return (string) ($this->argument('password') ?? $this->secret('Enter password for the admin user'));
    }
}
