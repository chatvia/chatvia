<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Admin\Settings\Actions\SetSettingAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Sets one value of the admin panel's Settings from the command line, for
 * installers and scripts. A secret (an API key or a password) is read from
 * standard input when no value is given, so it never shows in the shell
 * history: `printf %s "$KEY" | php artisan app:setting ai.mistral_api_key`.
 */
#[Description('Set one value of the admin panel Settings (key: group.property). --unset removes the saved value.')]
#[Signature('app:setting {key : group.property, such as mail.smtp_host} {value? : The value; read from standard input when left out} {--unset : Remove the saved value so the default applies}')]
class SetSetting extends Command
{
    public function handle(SetSettingAction $setSetting): int
    {
        $key = (string) $this->argument('key');
        $value = $this->option('unset') ? null : $this->value();

        if (! $this->option('unset') && blank($value)) {
            $this->error('Give a value, pipe it on standard input, or use --unset.');

            return self::FAILURE;
        }

        try {
            $setSetting->execute($key, $value);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($value === null ? "{$key} removed: its default applies." : "{$key} saved.");

        return self::SUCCESS;
    }

    private function value(): ?string
    {
        $value = $this->argument('value');

        if (is_string($value)) {
            return $value;
        }

        if ($this->input->isInteractive() && stream_isatty(STDIN)) {
            return (string) $this->secret('Value');
        }

        $piped = stream_get_contents(STDIN);

        return $piped === false ? null : rtrim($piped, "\r\n");
    }
}
