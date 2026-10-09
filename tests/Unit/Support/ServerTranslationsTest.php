<?php

declare(strict_types=1);

/*
 * Server-side translations: the English-as-key `lang/{locale}.json` files that
 * back `__()` in mail, notifications, validation and flash messages. A string
 * missing from a locale silently falls back to English, so these checks keep
 * every locale complete.
 */

/** @return array<string, array<string, string>> locale => [English key => translation] */
$translationFiles = function (): array {
    $files = [];

    foreach (glob(lang_path('*.json')) ?: [] as $path) {
        $files[basename($path, '.json')] = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    return $files;
};

/** The value of a PHP string literal ('…' or "…" without interpolation). */
$unquote = fn (string $literal): string => $literal[0] === "'"
    ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($literal, 1, -1))
    : stripcslashes(substr($literal, 1, -1));

/**
 * The literal first argument of every `__()`, `trans()`, `trans_choice()` and
 * `Lang::get()` call in a PHP file. Calls with a variable key are skipped.
 *
 * @return list<string>
 */
$keysInPhp = function (string $code) use ($unquote): array {
    $tokens = array_values(array_filter(
        token_get_all($code),
        fn (array|string $token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
    ));
    $keys = [];

    foreach ($tokens as $i => $token) {
        if (! is_array($token) || ! in_array(ltrim($token[1], '\\'), ['__', 'trans', 'trans_choice', 'get'], true)) {
            continue;
        }

        $previous = $tokens[$i - 1] ?? null;
        $isLangGet = is_array($previous) && $previous[0] === T_DOUBLE_COLON && ltrim((string) ($tokens[$i - 2][1] ?? ''), '\\') === 'Lang';
        $isMethodOrDefinition = is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true);

        if ($token[1] === 'get' ? ! $isLangGet : $isMethodOrDefinition) {
            continue;
        }

        $argument = $tokens[$i + 2] ?? null;

        if (($tokens[$i + 1] ?? null) === '('
            && is_array($argument) && $argument[0] === T_CONSTANT_ENCAPSED_STRING
            && in_array($tokens[$i + 3] ?? null, [',', ')'], true)) {
            $keys[] = $unquote($argument[1]);
        }
    }

    return $keys;
};

/**
 * The literal first argument of every `__()`, `trans()`, `trans_choice()` and
 * `@lang()` call in a Blade template.
 *
 * @return list<string>
 */
$keysInBlade = function (string $template) use ($unquote): array {
    preg_match_all('/(?:@lang|\b__|\btrans_choice|\btrans)\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")\s*[,)]/s', $template, $matches);

    return array_map($unquote, $matches[1]);
};

it('has the same keys in every server-side locale file', function () use ($translationFiles): void {
    $files = $translationFiles();
    $allKeys = array_unique(array_merge(...array_map(array_keys(...), array_values($files))));

    expect($files)->not->toBeEmpty();

    foreach ($files as $locale => $translations) {
        expect(array_values(array_diff($allKeys, array_keys($translations))))
            ->toBe([], "lang/{$locale}.json is missing keys that other locales have");
    }
});

it('translates every literal __() string in src/ and the views into every locale', function () use ($translationFiles, $keysInPhp, $keysInBlade): void {
    // `auth.failed`-style keys live in the lang/{locale}/*.php group files instead.
    $groupFiles = ['auth', 'landing', 'pagination', 'passwords', 'validation'];
    $used = [];

    foreach ([base_path('src'), resource_path('views')] as $root) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = (string) $file;

            $keys = match (true) {
                str_ends_with($path, '.blade.php') => $keysInBlade((string) file_get_contents($path)),
                str_ends_with($path, '.php') => $keysInPhp((string) file_get_contents($path)),
                default => [],
            };

            foreach ($keys as $key) {
                if (preg_match('/^([a-z_]+)\.[\w.-]+$/', $key, $group) === 1 && in_array($group[1], $groupFiles, true)) {
                    continue;
                }

                $used[$key] ??= str_replace(base_path().'/', '', $path);
            }
        }
    }

    expect($used)->not->toBeEmpty();

    foreach ($translationFiles() as $locale => $translations) {
        expect(array_diff_key($used, $translations))
            ->toBe([], "lang/{$locale}.json has no translation for these strings (string => first file using it)");
    }
});
