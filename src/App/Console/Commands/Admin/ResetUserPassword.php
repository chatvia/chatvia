<?php

declare(strict_types=1);

namespace App\Console\Commands\Admin;

use App\Identity\Workspace\Actions\Account\DisableTwoFactorAction;
use App\Identity\Workspace\Actions\Account\UpdatePasswordAction;
use App\Identity\Workspace\Data\Account\UpdatePasswordData;
use App\Identity\Workspace\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * Sets a new password for an account from the command line, for someone who
 * is locked out: a self-hosted install often has no e-mail for "Forgot
 * password". Run with no arguments, it asks for the email, and for an
 * account with two-factor sign-in whether to turn that off too. The password
 * is asked for when it is not passed; `--stdin` reads it from standard input
 * instead, so a script (the Docker install's `manage`) can pass it without it
 * showing in the process list.
 */
#[Description('Set a new password for a user')]
#[Signature('chatvia:admin:reset-password {email : Email address of the account} {password? : The new password (asked for when left out)} {--stdin : Read the password from standard input} {--disable-two-factor : Also turn off two-factor sign-in for the account}')]
class ResetUserPassword extends Command implements PromptsForMissingInput
{
    public function handle(UpdatePasswordAction $updatePassword, DisableTwoFactorAction $disableTwoFactor): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', Str::lower($email))->first();

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

    /**
     * @return array<string, \Closure(): string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'email' => fn (): string => text(
                label: 'Email address of the account',
                placeholder: 'admin@example.com',
                validate: ['email' => 'required|email'],
            ),
        ];
    }

    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getOption('disable-two-factor')) {
            return;
        }

        $user = User::query()->where('email', Str::lower((string) $input->getArgument('email')))->first();

        if ($user?->usesTwoFactor()) {
            $input->setOption('disable-two-factor', confirm(
                label: 'This account uses two-factor sign-in. Turn it off too?',
                default: false,
            ));
        }
    }

    private function password(): string
    {
        if ($this->option('stdin')) {
            $line = fgets(STDIN);

            return $line === false ? '' : Str::rtrim($line, "\r\n");
        }

        return (string) ($this->argument('password') ?? $this->secret('New password'));
    }
}
