<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Support\Str;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

/**
 * A group of values an admin manages on one page of the admin panel's
 * Settings. Every property is nullable: null means "not set here", so its
 * config key keeps the value from config/*.php. Each property names the config
 * key it fills with {@see ConfigKey}; secrets carry {@see ShouldBeEncrypted},
 * which also makes them write-only in the panel.
 *
 * The rows are created by the settings migrations in database/settings, one
 * per class. Never rename a group or a property without one: both are stored.
 */
abstract class AdminSettings extends Settings
{
    /** @var array<class-string<self>, array<string, list<ConfigKey>>> */
    private static array $configKeys = [];

    /** @var array<class-string<self>, list<string>> */
    private static array $secrets = [];

    /**
     * The config keys of each property, by property name.
     *
     * @return array<string, list<ConfigKey>>
     */
    public static function configKeys(): array
    {
        return self::$configKeys[static::class] ??= collect(self::properties())
            ->mapWithKeys(fn (ReflectionProperty $property): array => [
                $property->getName() => array_map(
                    fn (ReflectionAttribute $attribute): ConfigKey => $attribute->newInstance(),
                    $property->getAttributes(ConfigKey::class),
                ),
            ])
            ->all();
    }

    /** The property that fills a config key, or null when none does. */
    public static function propertyFor(string $configKey): ?string
    {
        foreach (static::configKeys() as $property => $keys) {
            foreach ($keys as $key) {
                if ($key->key === $configKey) {
                    return $property;
                }
            }
        }

        return null;
    }

    /**
     * The properties that are stored encrypted and never sent to the browser.
     *
     * @return list<string>
     */
    public static function secrets(): array
    {
        return self::$secrets[static::class] ??= array_values(array_map(
            fn (ReflectionProperty $property): string => $property->getName(),
            array_filter(
                self::properties(),
                fn (ReflectionProperty $property): bool => $property->getAttributes(ShouldBeEncrypted::class) !== [],
            ),
        ));
    }

    public static function isSecret(string $property): bool
    {
        return collect(static::secrets())->containsStrict($property);
    }

    /**
     * The PHP type of a property without its `?`: string, int, float or bool.
     */
    public static function typeOf(string $property): string
    {
        $type = new ReflectionProperty(static::class, $property)->getType();

        return $type instanceof ReflectionNamedType ? $type->getName() : 'string';
    }

    /**
     * Input from a form or the command line as the property's type. Blank
     * input, and input that is not valid for the type, become null. An array
     * becomes a list of its non-empty items.
     *
     * @return string|int|float|bool|list<string>|null
     */
    public static function cast(string $property, mixed $value): string|int|float|bool|array|null
    {
        if (static::typeOf($property) === 'array') {
            $value = (array) $value;
            $items = array_filter(
                array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $value),
                fn (string $item): bool => $item !== '',
            );

            if ($items === []) {
                return null;
            }

            return array_values($items);
        }

        if (blank($value) || ! is_scalar($value)) {
            return null;
        }

        return match (static::typeOf($property)) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'float' => is_numeric($value) ? (float) $value : null,
            default => Str::trim((string) $value),
        };
    }

    /** @return list<ReflectionProperty> */
    private static function properties(): array
    {
        return array_values(array_filter(
            new ReflectionClass(static::class)->getProperties(ReflectionProperty::IS_PUBLIC),
            fn (ReflectionProperty $property): bool => ! $property->isStatic() && $property->getDeclaringClass()->getName() === static::class,
        ));
    }
}
