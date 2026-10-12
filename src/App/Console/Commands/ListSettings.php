<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Admin\Settings\AdminSettings;
use App\Admin\Settings\SettingsConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Lists the keys of the admin panel's Settings with the value in use, for
 * scripts and for `app:setting`. Secrets only show whether they are saved.
 */
#[Description('List the admin panel Settings and their values. Secrets are never shown.')]
#[Signature('app:settings {group? : Only this group, such as mail}')]
class ListSettings extends Command
{
    public function handle(SettingsConfig $settingsConfig): int
    {
        $rows = [];

        foreach ($settingsConfig->classes() as $class) {
            if ($this->argument('group') !== null && $class::group() !== $this->argument('group')) {
                continue;
            }

            /** @var AdminSettings $settings */
            $settings = app($class);

            foreach ($settings->toArray() as $property => $saved) {
                $rows[] = [
                    "{$class::group()}.{$property}",
                    $this->show($class, $property, $saved ?? $settingsConfig->defaultOf($class, $property)),
                    filled($saved) ? 'saved' : 'default',
                ];
            }
        }

        $this->table(['Key', 'Value', 'Source'], $rows);

        return self::SUCCESS;
    }

    /**
     * @param  class-string<AdminSettings>  $class
     */
    private function show(string $class, string $property, mixed $value): string
    {
        return match (true) {
            $class::isSecret($property) => filled($value) ? '********' : '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
