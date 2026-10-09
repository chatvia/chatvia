<?php

namespace Support\Autoload;

/**
 * Keeps class names that only changed their letter case loadable under the
 * old spelling.
 *
 * Some namespaces were renamed to Pascal-case acronyms (`QA` → `Qa`,
 * `OCR` → `Ocr`). The old spelling still exists outside the code: queued job
 * payloads and cached values hold serialized objects, and a shipped migration
 * imports a model by its old name. PHP class names are case-insensitive, but
 * the autoloader maps names to file paths, and on a case-sensitive file system
 * the old path no longer exists. This loader catches the old spelling and loads
 * the current class, which then satisfies the old name too.
 */
final class LegacyNamespaceAutoloader
{
    /**
     * Old namespace prefix => current namespace prefix. Only case-only renames
     * belong here; a real rename needs a subclass or `class_alias()` instead.
     *
     * @var array<string, string>
     */
    private const array PREFIXES = [
        'Domain\\Knowledge\\Source\\QA\\' => 'Domain\\Knowledge\\Source\\Qa\\',
        'Domain\\Knowledge\\Ingestion\\Data\\OCR\\' => 'Domain\\Knowledge\\Ingestion\\Data\\Ocr\\',
    ];

    private static bool $registered = false;

    public static function register(): void
    {
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
        foreach (self::PREFIXES as $legacy => $current) {
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
