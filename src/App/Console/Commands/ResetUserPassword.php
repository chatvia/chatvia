<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Identity\Workspace\Actions\Account\DisableTwoFactorAction;
use App\Identity\Workspace\Actions\Account\UpdatePasswordAction;
use App\Identity\Workspace\Data\Account\UpdatePasswordData;
use App\Identity\Workspace\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sets a new password for an account from the command line, for someone who
 * is locked out: a self-hosted install often has no e-mail for "Forgot
 * password". The password is asked for when it is not passed; `--stdin` reads
 * it from standard input instead, so a script (the Docker install's `manage`)
 * can pass it without it showing in the process list.
 */
#[Description('Set a new password for a user')]
#[Signature('admin:reset-password {email} {password?} {--stdin : Read the password from standard input} {--disable-two-factor : Also turn off two-factor sign-in for the account}')]
class ResetUserPassword extends Command
{
    public function handle(UpdatePasswordAction $updatePassword, DisableTwoFactorAction $disableTwoFactor): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->whereRaw('lower(email) = ?', [strtolower($email)])->first();

        if ($user === null) {
            $this->error("There is no account with the email {$email}.");

            return self::FAILURE;
        }

        $password = $this->password();
        if ($password === '') {
            $this->error('The password is empty.');

            return self::FAILURE;
        }

        $updatePassword->execute(new UpdatePasswordData(user: $user, password: $password));
        $this->info("The password of {$user->email} is changed.");

        if ($this->option('disable-two-factor')) {
            $disableTwoFactor->execute($user);
            $this->info('Two-factor sign-in is turned off for this account.');
        }

        return self::SUCCESS;
    }

    private function password(): string
    {
        if ($this->option('stdin')) {
            $line = fgets(STDIN);

            return $line === false ? '' : rtrim($line, "\r\n");
        }

        return (string) ($this->argument('password') ?? $this->secret('New password'));
    }
}
