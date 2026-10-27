<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\AdminSettings;
use App\Admin\Settings\SettingsConfig;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Saves one admin-panel setting by its key (`group.property`, such as
 * `mail.smtp_host`), so installers and scripts can set up an install without
 * the panel. A null value removes the saved value and the default applies
 * again. The value is cast to the property's type, and secrets are stored
 * encrypted, as in the panel.
 */
final readonly class SetSettingAction
{
    public function __construct(
        private SettingsConfig $settingsConfig,
        private Container $container,
    ) {}

    public function execute(string $key, ?string $value): AdminSettings
    {
        [$class, $property] = $this->resolve($key);

        $cast = $class::cast($property, $value);

        if ($cast === null && filled($value)) {
            throw new InvalidArgumentException("[{$value}] is not a valid value for [{$key}], which takes a {$class::typeOf($property)}.");
        }

        /** @var AdminSettings $settings */
        $settings = $this->container->make($class);
        $settings->{$property} = $cast;
        $settings->save();

        return $settings;
    }

    /**
     * @return array{class-string<AdminSettings>, string}
     */
    private function resolve(string $key): array
    {
        [$group, $property] = Str::of($key)->explode('.', 2)->pad(2, '')->all();

        foreach ($this->settingsConfig->classes() as $class) {
            if ($class::group() === $group && array_key_exists($property, $class::configKeys())) {
                return [$class, $property];
            }
        }

        throw new InvalidArgumentException("Unknown setting [{$key}]. Run `php artisan chatvia:settings:list` to list them.");
    }
}
