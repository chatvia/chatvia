<?php

declare(strict_types=1);

namespace Support\Autoload;

/**
 * Keeps class names that only changed their letter case loadable under the
 * old spelling.
 *
 * When a namespace is renamed to a Pascal-case acronym (`QA` → `Qa`), the old
 * spelling can still exist outside the code: queued job payloads and cached
 * values hold serialized objects, and shipped migrations import classes by
 * name. PHP class names are case-insensitive, but the autoloader maps names
 * to file paths, and on a case-sensitive file system the old path no longer
 * exists. This loader catches the old spelling and loads the current class,
 * which then satisfies the old name too.
 *
 * The application registers its renamed prefixes at boot. Only case-only
 * renames belong here; a real rename needs a subclass or `class_alias()`.
 */
final class LegacyNamespaceAutoloader
{
    /** @var array<string, string> Old namespace prefix => current namespace prefix. */
    private static array $prefixes = [];

    private static bool $registered = false;

    /**
     * Add renamed prefixes and register the loader (once per process).
     *
     * @param  array<string, string>  $prefixes  Old namespace prefix => current
     *                                           namespace prefix, both ending in `\`.
     */
    public static function register(array $prefixes): void
    {
        self::$prefixes = [...self::$prefixes, ...$prefixes];

        if (self::$registered) {
            return;
        }

        spl_autoload_register(self::load(...));

        self::$registered = true;
    }

    /**
     * The current name for a class spelled with an old prefix, or null when the
     * name is not a legacy spelling.
     */
    public static function currentName(string $class): ?string
    {
        foreach (self::$prefixes as $legacy => $current) {
            if (str_starts_with($class, $legacy)) {
                return $current.substr($class, strlen($legacy));
            }
        }

        return null;
    }

    private static function load(string $class): void
    {
        $current = self::currentName($class);

        if ($current !== null) {
            class_exists($current);
        }
    }
}
