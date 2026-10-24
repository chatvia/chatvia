<?php

declare(strict_types=1);

namespace Tests\Support\Config;

/**
 * A config file evaluated again with the given environment, as when the
 * config loads after .env: for the tests of what a file reads from env. The
 * environment is put back as it was afterwards, absent where it was absent.
 */
final class ConfigFile
{
    /**
     * @param  string  $file  The file under config/, such as "filesystems.php".
     * @param  array<string, string|null>  $env  The variables to set; null unsets one.
     */
    public static function with(string $file, array $env): mixed
    {
        $saved = [];

        foreach ($env as $key => $value) {
            $saved[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];
            self::set($key, $value, $value, $value ?? false);
        }

        try {
            return require config_path($file);
        } finally {
            foreach ($saved as $key => [$server, $envValue, $process]) {
                self::set($key, $server, $envValue, $process);
            }
        }
    }

    private static function set(string $key, ?string $server, ?string $env, string|false $process): void
    {
        if ($server === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $server;
        }

        if ($env === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $env;
        }

        $process === false ? putenv($key) : putenv("{$key}={$process}");
    }
}
