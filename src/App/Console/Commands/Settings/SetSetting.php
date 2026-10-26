<?php

declare(strict_types=1);

namespace App\Console\Commands\Settings;

use App\Admin\Settings\Actions\SetSettingAction;
use App\Admin\Settings\SettingsConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use InvalidArgumentException;

use function Laravel\Prompts\suggest;

/**
 * Sets one value of the admin panel's Settings from the command line, for
 * installers and scripts. A secret (an API key or a password) is read from
 * standard input when no value is given, so it never shows in the shell
 * history: `printf %s "$KEY" | php artisan chatvia:settings:set ai.mistral_api_key`.
 * Run with no arguments, it asks for the key (suggesting the known ones) and
 * then the value.
 */
#[Description('Set one value of the admin panel Settings (key: group.property). --unset removes the saved value.')]
#[Signature('chatvia:settings:set {key : group.property, such as mail.smtp_host} {value? : The value; read from standard input when left out} {--unset : Remove the saved value so the default applies}')]
class SetSetting extends Command implements PromptsForMissingInput
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

    /**
     * @return array<string, \Closure(): string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        $keys = collect($this->laravel->make(SettingsConfig::class)->classes())
            ->flatMap(fn (string $class): array => collect($class::configKeys())
                ->keys()
                ->map(fn (string $property): string => $class::group().'.'.$property)
                ->all())
            ->values();

        return [
            'key' => fn (): string => suggest(
                label: 'Setting key',
                options: $keys->all(),
                placeholder: 'mail.smtp_host',
                required: true,
                validate: fn (string $key): ?string => $keys->contains($key)
                    ? null
                    : 'Unknown setting. Run `php artisan chatvia:settings:list` to list them.',
            ),
        ];
    }

    private function value(): ?string
    {
        $value = $this->argument('value');

        if ($value !== null) {
            return $value;
        }

        if ($this->input->isInteractive() && stream_isatty(STDIN)) {
            return (string) $this->secret('Value');
        }

        $piped = stream_get_contents(STDIN);

        return $piped === false ? null : Str::rtrim($piped, "\r\n");
    }
}
