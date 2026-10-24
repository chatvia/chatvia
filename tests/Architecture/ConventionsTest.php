<?php

use App\Http\Rules\WorkspaceRule;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Support\Str;

/*
 * Executable form of the layering and naming rules in ARCHITECTURE.md. Each
 * rule would otherwise be a manual grep a reviewer has to remember to run;
 * here it fails CI instead, so the layering can't silently erode.
 *
 * Lives in the Architecture suite, which runs framework-free (no app container,
 * no DB) — so source files are located by path relative to this file, exactly
 * like ActionsHaveTestsTest.
 */

/** @return list<string> absolute paths of every .php file under src/<relativeDir>. */
function conventionPhpFiles(string $relativeDir): array
{
    $base = realpath(__DIR__.'/../../'.$relativeDir);

    if ($base === false) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && Str::endsWith((string) $file, '.php')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/**
 * Files whose path contains /<segment>/ anywhere under src/: the Domain, the
 * Identity carve-out and the admin panel's own Actions, Jobs and Listeners.
 */
function conventionFilesInSegment(string $segment): array
{
    $needle = DIRECTORY_SEPARATOR.$segment.DIRECTORY_SEPARATOR;

    return array_values(array_filter(
        conventionPhpFiles('src'),
        fn (string $path): bool => Str::contains($path, $needle),
    ));
}

/**
 * Relative paths of the given files whose contents match $pattern — the failure
 * message then points straight at the offending file.
 *
 * @param  list<string>  $files
 * @return list<string>
 */
function conventionOffenders(array $files, string $pattern): array
{
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $offenders = [];

    foreach ($files as $path) {
        if (Str::isMatch($pattern, (string) file_get_contents($path))) {
            $offenders[] = Str::replace($root, '', $path);
        }
    }

    sort($offenders);

    return $offenders;
}

/**
 * Qualified class names in $path's code (imports and references, not comments
 * or strings) that match $pattern.
 *
 * @return list<string>
 */
function conventionQualifiedNames(string $path, string $pattern): array
{
    $names = [];

    foreach (PhpToken::tokenize((string) file_get_contents($path)) as $token) {
        if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && Str::isMatch($pattern, $token->text)) {
            $names[] = $token->text;
        }
    }

    return $names;
}

// --- Dependency direction ------------------------------------------------

it('the Domain layer does not import the HTTP request object', function (): void {
    // Sanctioned HTTP→domain boundary adapters (see ARCHITECTURE.md).
    $allowed = [
        'src/Domain/Widget/Services/WidgetVerification.php',
        'src/Domain/Conversation/Services/Streaming/Requests/StreamRequestDataFactory.php',
    ];

    // Imports (aliased or not) and fully-qualified references in code count;
    // comments do not.
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $offenders = [];

    foreach (conventionPhpFiles('src/Domain') as $path) {
        if (conventionQualifiedNames($path, '/^\\\\?Illuminate\\\\Http\\\\Request$/') !== []) {
            $offenders[] = Str::replace($root, '', $path);
        }
    }

    expect(array_values(array_diff($offenders, $allowed)))->toBe([]);
});

it('the Domain layer never imports from App except the Identity carve-out', function (): void {
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $offenders = [];

    foreach (conventionPhpFiles('src/Domain') as $path) {
        foreach (conventionQualifiedNames($path, '/^\\\\?App\\\\(?!Identity\\\\)/') as $name) {
            $offenders[] = Str::replace($root, '', $path).' → '.$name;
        }
    }

    sort($offenders);

    expect($offenders)->toBe([]);
});

it('the Integration domain never depends on the Conversation domain', function (): void {
    // Conversation runs chat turns and calls tools through Integration\Tool;
    // the reverse edge would make the two domains a cycle again. A tool that
    // needs conversation data receives it as a value (see ToolCallData).
    $files = conventionPhpFiles('src/Domain/Integration');

    expect($files)->not->toBe([])
        ->and(conventionOffenders($files, '/\bDomain\\\\Conversation\\\\/'))->toBe([]);
});

/**
 * Class names in $path's code (imports and qualified references, not comments
 * or strings) that point into the Domain or App namespace.
 *
 * @return list<string>
 */
function conventionDomainOrAppReferences(string $path): array
{
    $tokens = PhpToken::tokenize((string) file_get_contents($path));
    $references = [];

    foreach ($tokens as $index => $token) {
        $qualified = $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
            && Str::isMatch('/^\\\\?(Domain|App)\\\\/', $token->text);

        // A group import (`use Domain\{A, B};`) starts with a bare name and a separator.
        $groupPrefix = $token->is(T_STRING)
            && in_array($token->text, ['Domain', 'App'], true)
            && ($tokens[$index + 1] ?? null)?->is(T_NS_SEPARATOR) === true;

        if ($qualified || $groupPrefix) {
            $references[] = $token->text;
        }
    }

    return $references;
}

it('the Support layer never depends on Domain or App', function (): void {
    // Support holds small framework-level helpers that every layer may use.
    // A reference to Domain\ or App\ would put business rules in a helper and
    // make the layers depend on each other in a circle. Comments may still
    // name such classes; only code references count. No exceptions.
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $files = conventionPhpFiles('src/Support');
    $offenders = [];

    foreach ($files as $path) {
        foreach (conventionDomainOrAppReferences($path) as $reference) {
            $offenders[] = Str::replace($root, '', $path).' → '.$reference;
        }
    }

    expect($files)->not->toBe([])
        ->and($offenders)->toBe([]);
});

// --- Action conventions --------------------------------------------------

it('Actions expose execute(), never handle()', function (): void {
    expect(conventionOffenders(conventionFilesInSegment('Actions'), '/function handle\(/'))->toBe([]);
});

it('Actions never return an HTTP redirect', function (): void {
    expect(conventionOffenders(conventionFilesInSegment('Actions'), '/return redirect/'))->toBe([]);
});

it('Actions never reach for the Auth facade', function (): void {
    expect(conventionOffenders(conventionFilesInSegment('Actions'), '/Auth::/'))->toBe([]);
});

it('Actions never send notifications inline (listeners do that)', function (): void {
    expect(conventionOffenders(conventionFilesInSegment('Actions'), '/->notify\(|Notification::route/'))->toBe([]);
});

// --- Job conventions -----------------------------------------------------

it('Jobs hold no business logic (no DB transactions or row locks)', function (): void {
    // ARCHITECTURE.md: "Jobs hold no logic. A job receives a DTO (or models)
    // in its constructor and calls an Action in handle()." A DB::transaction /
    // lockForUpdate inside a Job is the tell-tale of business logic that
    // belongs in an Action. Guarding it here stops the pattern from being
    // copied into the next job.
    expect(conventionOffenders(
        conventionFilesInSegment('Jobs'),
        '/DB::transaction\(|DB::beginTransaction\(|->lockForUpdate\(/',
    ))->toBe([]);
});

// --- Naming conventions --------------------------------------------------

it('listeners drop the redundant Listener suffix', function (): void {
    expect(conventionOffenders(conventionFilesInSegment('Listeners'), '/class\s+\w*Listener\b/'))->toBe([]);
});

it('Domain models do not carry HTTP-leakage Request names', function (): void {
    $models = array_values(array_filter(
        conventionPhpFiles('src/Domain'),
        fn (string $path): bool => Str::contains($path, DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR),
    ));

    // A class named "...Request" but not "...Requests".
    expect(conventionOffenders($models, '/class\s+\w*Request(?!s)\b/'))->toBe([]);
});

// --- HTTP-layer conventions ---------------------------------------------

/**
 * Controllers MUST use FormRequest + ->toData() instead of inline
 * `$request->validate(...)`. This is a shrink-only allowlist (matching the
 * pattern used by ActionsHaveTestsTest): the list pins the *current* set of
 * offenders so any NEW controller method calling `$request->validate(` fails
 * the test, and once a listed file is migrated it must be removed (or the
 * "drift down" branch flags it).
 *
 * Pure read-only `index`/`show` are not in scope — ARCHITECTURE.md exempts those
 * from the Action layer and `$request->validate` is allowed there. We catch
 * everything else by globbing the controllers tree.
 */
const INLINE_VALIDATE_ALLOWLIST = [
];

it('controllers use FormRequest + toData() instead of inline $request->validate()', function (): void {
    $controllers = conventionPhpFiles('src/App/Http/Controllers');
    $offenders = conventionOffenders($controllers, '/\$request->validate\(/');

    // Drift up: a NEW controller calling inline validate must be migrated or
    // explicitly added to the allowlist (which shows up in code review).
    $newOffenders = array_values(array_diff($offenders, INLINE_VALIDATE_ALLOWLIST));
    expect($newOffenders)->toBe([], implode("\n", [
        '',
        'New controller(s) using inline $request->validate():',
        ...array_map(fn (string $f): string => "  - {$f}", $newOffenders),
        '',
        'Use a FormRequest with toData() instead (see ARCHITECTURE.md, "How a request flows")',
        'or add to INLINE_VALIDATE_ALLOWLIST in this file with a follow-up ticket.',
    ]));

    // Drift down: a listed file no longer calls inline validate → drop it
    // from the allowlist so the list shrinks monotonically.
    $nowClean = array_values(array_diff(INLINE_VALIDATE_ALLOWLIST, $offenders));
    expect($nowClean)->toBe([], implode("\n", [
        '',
        'Files in INLINE_VALIDATE_ALLOWLIST no longer call $request->validate() — remove:',
        ...array_map(fn (string $f): string => "  - {$f}", $nowClean),
    ]));
});

/**
 * Controllers are resource controllers or single-action controllers: their
 * public methods are the seven resource verbs or `__invoke`. Any other action
 * (export, connect, rewrite, …) gets its own invokable controller, so a
 * controller never grows into a grab bag of unrelated endpoints.
 */
const CONTROLLER_PUBLIC_METHODS = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', '__invoke', '__construct'];

it('controllers expose only resource methods or __invoke', function (): void {
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $offenders = [];

    foreach (conventionPhpFiles('src/App/Http/Controllers') as $path) {
        if (Str::contains($path, DIRECTORY_SEPARATOR.'Concerns'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $methods = Str::matchAll('/^\s*public\s+(?:static\s+)?function\s+(\w+)\s*\(/m', (string) file_get_contents($path));

        foreach ($methods->diff(CONTROLLER_PUBLIC_METHODS) as $method) {
            $offenders[] = Str::replace($root, '', $path).'::'.$method.'()';
        }
    }

    expect($offenders)->toBe([], implode("\n", [
        '',
        'Controller methods that are neither a resource verb nor __invoke:',
        ...array_map(fn (string $o): string => "  - {$o}", $offenders),
        '',
        'Rename it to the matching resource verb, or move it into its own invokable controller.',
    ]));
});

// --- Multi-tenant lookup discipline -------------------------------------

/**
 * Inline Conversation::where('external_id', …) and Session::where('external_id', …)
 * lookups skip the agent-scoping that {@see WidgetVerification}
 * exists to centralise. A call site that has to look one up some other way is
 * listed below with its reason; a new one goes through WidgetVerification, so
 * the cross-tenant defence stays in one place.
 */
const EXTERNAL_ID_LOOKUP_ALLOWLIST = [
    // Scoped in the query itself: the visitor's session and the agent.
    'src/Domain/Conversation/Actions/Conversations/FindWidgetConversationAction.php',
    'src/Domain/Conversation/Services/Memory/ChatMemory.php',
    'src/Domain/Widget/Services/BroadcastAuthorizer.php',
    // The turn's own conversation, whose id was checked when the turn
    // started; they read only its surface and id.
    'src/Domain/Conversation/Services/Tooling/Routing/ApprovalToolFilter.php',
    'src/Domain/Conversation/Services/Tooling/Routing/StickyToolResolver.php',
    'src/Domain/Conversation/Services/Tooling/Routing/SurfaceToolFilter.php',
];

it('Conversation/Session external_id lookups go through WidgetVerification', function (): void {
    // WidgetVerification is where these lookups belong.
    $sources = array_values(array_filter(
        [...conventionPhpFiles('src/Domain'), ...conventionPhpFiles('src/App/Http/Controllers')],
        fn (string $path): bool => ! Str::endsWith($path, 'src/Domain/Widget/Services/WidgetVerification.php'),
    ));

    // The chain may be split over lines: Conversation::query()
    //     ->where('external_id', …).
    $offenders = conventionOffenders(
        $sources,
        '/(?:Conversation|Session)::(?:query\(\)\s*->\s*)?(?:where|firstWhere)\(\s*[\'"]external_id[\'"]/',
    );

    $newOffenders = array_values(array_diff($offenders, EXTERNAL_ID_LOOKUP_ALLOWLIST));
    expect($newOffenders)->toBe([], implode("\n", [
        '',
        'New inline external_id lookup(s) — route through WidgetVerification with the agent:',
        ...array_map(fn (string $f): string => "  - {$f}", $newOffenders),
        '',
        'Or, if there is a clear reason it cannot, add to EXTERNAL_ID_LOOKUP_ALLOWLIST in this file.',
    ]));

    $nowClean = array_values(array_diff(EXTERNAL_ID_LOOKUP_ALLOWLIST, $offenders));
    expect($nowClean)->toBe([], implode("\n", [
        '',
        'Allowlisted file no longer has an inline external_id lookup — remove from EXTERNAL_ID_LOOKUP_ALLOWLIST:',
        ...array_map(fn (string $f): string => "  - {$f}", $nowClean),
    ]));
});

/**
 * Form requests look ids and names up inside the caller's workspace with
 * {@see WorkspaceRule}. A bare `exists:`/`unique:` rule, or a
 * raw Rule::exists()/Rule::unique(), checks the whole table: another
 * workspace's id passes validation and its names look taken. Listed here:
 * values that are unique across the install, and rules scoped to a parent
 * record the route already checks.
 */
const BARE_DATABASE_RULE_ALLOWLIST = [
    'src/App/Http/Requests/Agent/UpdateAgentDomainRequest.php', // a custom chat domain is install-wide
    'src/App/Http/Requests/Auth/RegisterUserRequest.php', // an account email is install-wide
    'src/App/Http/Requests/Settings/Account/ProfileUpdateRequest.php', // an account email is install-wide
];

it('form requests scope exists/unique rules to the workspace', function (): void {
    $offenders = conventionOffenders(
        conventionPhpFiles('src/App/Http/Requests'),
        '/[\'"](?:exists|unique):|(?<![\w\\\\])Rule::(?:exists|unique)\(/',
    );

    $newOffenders = array_values(array_diff($offenders, BARE_DATABASE_RULE_ALLOWLIST));
    expect($newOffenders)->toBe([], implode("\n", [
        '',
        'New bare exists/unique rule(s) — use WorkspaceRule::exists()/unique():',
        ...array_map(fn (string $f): string => "  - {$f}", $newOffenders),
        '',
        'Or, for a value that is unique across the install, add to BARE_DATABASE_RULE_ALLOWLIST in this file.',
    ]));

    $nowClean = array_values(array_diff(BARE_DATABASE_RULE_ALLOWLIST, $offenders));
    expect($nowClean)->toBe([], implode("\n", [
        '',
        'Allowlisted file no longer has a bare exists/unique rule — remove from BARE_DATABASE_RULE_ALLOWLIST:',
        ...array_map(fn (string $f): string => "  - {$f}", $nowClean),
    ]));
});

// --- Code style ----------------------------------------------------------

/**
 * Empty checks use Laravel's filled()/blank() (.ai/rules/src.md): writing out
 * `is_string($x) && $x !== ''` or `$x !== null && $x !== ''` repeats what the
 * helpers already say. Listed: files where filled() is wrong, with how many
 * hand-written checks each may keep.
 */
const HAND_WRITTEN_EMPTY_CHECK_ALLOWLIST = [
    // An empty list (e.g. no sites) is a real setting value; filled([]) is false.
    'src/App/Http/Requests/Agent/Settings/UpdateAgentSettingsRequest.php' => 1,
];

/**
 * Line numbers in $code that test for an empty value by hand:
 * `is_string($x) && $x !== ''`, `$x !== null && $x !== ''` (either order,
 * either polarity, with or without trim()) or `trim($x) === ''`.
 *
 * @return list<int>
 */
function conventionHandWrittenEmptyChecks(string $code): array
{
    $tokens = [
        // The tested value ($x, $this->x, $row['x']), named so the second
        // half of the check can refer back to it.
        '{x}' => '(?<x>\$\w+(?:\??->\w+|\[[^\]\n]*\])*)',
        '{same}' => '(?:\k<x>|trim\(\s*\k<x>\s*\))',
        '{empty}' => '(?:\'\'|"")',
        '{is}' => '\s*[!=]==\s*',
        '{and}' => '\s*(?:&&|\|\|)\s*',
    ];

    $patterns = [
        'is_string\(\s*{x}(?:\s*\?\?\s*null)?\s*\){and}{same}{is}{empty}', // is_string($x) && $x !== ''
        '{x}{is}null{and}{same}{is}{empty}', // $x !== null && $x !== ''
        '{x}{is}{empty}{and}\k<x>{is}null', // $x !== '' && $x !== null
        'trim\(\s*{x}\s*\){is}{empty}', // trim($x) === ''
    ];

    $lines = [];

    foreach ($patterns as $pattern) {
        preg_match_all('/'.strtr($pattern, $tokens).'/', $code, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [, $offset]) {
            $lines[] = substr_count($code, "\n", 0, $offset) + 1;
        }
    }

    $lines = array_values(array_unique($lines));
    sort($lines);

    return $lines;
}

it('empty checks use filled()/blank(), not hand-written comparisons with an empty string', function (): void {
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $found = [];

    foreach (conventionPhpFiles('src') as $path) {
        $lines = conventionHandWrittenEmptyChecks((string) file_get_contents($path));

        if ($lines !== []) {
            $found[Str::replace($root, '', $path)] = $lines;
        }
    }

    ksort($found);

    // Drift up: a new hand-written check, or one more in an allowlisted file.
    $newOffenders = [];

    foreach ($found as $file => $lines) {
        if (count($lines) > (HAND_WRITTEN_EMPTY_CHECK_ALLOWLIST[$file] ?? 0)) {
            $newOffenders[] = $file.':'.implode(',', $lines);
        }
    }

    expect($newOffenders)->toBe([], implode("\n", [
        '',
        'Hand-written empty check(s) — use filled()/blank():',
        ...array_map(fn (string $o): string => "  - {$o}", $newOffenders),
        '',
        'Or, where filled() is really wrong, add the file to HAND_WRITTEN_EMPTY_CHECK_ALLOWLIST in this file.',
    ]));

    // Drift down: an allowlisted check is gone, so the list must shrink too.
    $nowClean = [];

    foreach (HAND_WRITTEN_EMPTY_CHECK_ALLOWLIST as $file => $allowed) {
        if (count($found[$file] ?? []) < $allowed) {
            $nowClean[] = $file;
        }
    }

    expect($nowClean)->toBe([], implode("\n", [
        '',
        'Allowlisted file has fewer hand-written empty checks — lower or remove its HAND_WRITTEN_EMPTY_CHECK_ALLOWLIST entry:',
        ...array_map(fn (string $f): string => "  - {$f}", $nowClean),
    ]));
});

/**
 * Line numbers in $code that call is_string(), or pass it as a callable
 * (`is_string(...)`), but not a method of that name or a word in a comment.
 *
 * @return list<int>
 */
function conventionIsStringCalls(string $code): array
{
    $tokens = array_values(array_filter(
        PhpToken::tokenize($code),
        fn (PhpToken $token): bool => ! $token->isIgnorable(),
    ));
    $lines = [];

    foreach ($tokens as $i => $token) {
        if (! $token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) || Str::lower(Str::ltrim($token->text, '\\')) !== 'is_string') {
            continue;
        }

        if (($tokens[$i + 1] ?? null)?->text !== '(' || ($tokens[$i - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION])) {
            continue;
        }

        $lines[] = $token->line;
    }

    return $lines;
}

/**
 * No is_string() anywhere (.ai/rules/src.md): an empty check is filled() or
 * blank(), a string-or-array value is told apart with is_array(), and a
 * `string|false` result is compared with false. No allowlist: there is no
 * case left that needs it.
 */
it('checks values with filled()/blank(), never is_string()', function (): void {
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $offenders = [];

    foreach (['src', 'tests', 'config', 'routes', 'database', 'bootstrap'] as $dir) {
        foreach (conventionPhpFiles($dir) as $path) {
            if (Str::contains($path, DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $lines = conventionIsStringCalls((string) file_get_contents($path));

            if ($lines !== []) {
                $offenders[] = Str::replace($root, '', $path).':'.implode(',', $lines);
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", [
        '',
        'is_string() — check the value with filled()/blank(), or is_array() for string-or-array:',
        ...array_map(fn (string $o): string => "  - {$o}", $offenders),
    ]));
});

/**
 * Local files, outside programs, waits, clocks and ids go through Laravel
 * (.ai/rules/laravel-first.md): File, Process, Http, Sleep, now() and
 * Str::uuid7() can be faked in tests, the plain functions cannot. Listed:
 * files that still call one, with how many calls each may keep.
 */
const NATIVE_IO_CALL_ALLOWLIST = [
    // Streams: written or read piece by piece, which File has no call for.
    'src/App/Console/Commands/Db/BackupDatabase.php' => 1,
    'src/App/Identity/Workspace/Actions/DataExport/BuildDataExportArchiveAction.php' => 2,
    'src/Domain/Form/Actions/ExportSubmissionsCsvAction.php' => 1,
    'src/Domain/Knowledge/Ingestion/Actions/Website/CompleteWebsiteIngestionAction.php' => 1,
    'src/Support/Json/JsonArrayFile.php' => 1,
    'src/Domain/Knowledge/Document/Conversion/Markdown/CompoundFile.php' => 2,
    // Reads only the first bytes of the file.
    'src/Domain/Knowledge/Document/Conversion/BuiltinPdfInspector.php' => 1,
    // Silences the warning a path outside open_basedir gives.
    'src/Support/Executable.php' => 1,
    // Not converted yet.
    'src/Domain/Knowledge/Document/Conversion/Markdown/RtfReader.php' => 1,
    'src/Domain/Knowledge/Ingestion/Services/Parsing/LocalDocumentParser.php' => 1,
];

/** The plain PHP functions the rule replaces; every `curl_*` function too. */
const NATIVE_IO_FUNCTIONS = [
    'file_get_contents', 'file_put_contents', 'fopen', 'unlink', 'mkdir', 'rmdir',
    'is_file', 'file_exists', 'glob', 'exec', 'shell_exec', 'system', 'passthru',
    'proc_open', 'sleep', 'usleep', 'time', 'strtotime', 'uniqid',
];

/**
 * Line numbers in $code that call one of $functions, or a function whose name
 * starts with one of $prefixes: the name followed by `(` (a first-class
 * callable `trim(...)` counts too), but not a method (`->exec()`,
 * `File::glob()`), a function or constant being declared, or a word in a
 * comment or string.
 *
 * @param  list<string>  $functions
 * @param  list<string>  $prefixes
 * @return list<int>
 */
function conventionNativeCalls(string $code, array $functions, array $prefixes = []): array
{
    $tokens = array_values(array_filter(
        PhpToken::tokenize($code),
        fn (PhpToken $token): bool => ! $token->isIgnorable(),
    ));
    $lines = [];

    foreach ($tokens as $i => $token) {
        if (! $token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) || ($tokens[$i + 1] ?? null)?->text !== '(') {
            continue;
        }

        $name = Str::lower(Str::ltrim($token->text, '\\'));

        if (! in_array($name, $functions, true) && ! Str::startsWith($name, $prefixes)) {
            continue;
        }

        if (($tokens[$i - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST])) {
            continue;
        }

        $lines[] = $token->line;
    }

    return $lines;
}

it('local files, programs, waits and clocks go through Laravel, not plain PHP functions', function (): void {
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $found = [];

    foreach (conventionPhpFiles('src') as $path) {
        $lines = conventionNativeCalls((string) file_get_contents($path), NATIVE_IO_FUNCTIONS, ['curl_']);

        if ($lines !== []) {
            $found[Str::replace($root, '', $path)] = $lines;
        }
    }

    ksort($found);

    // Drift up: a new call, or one more in an allowlisted file.
    $newOffenders = [];

    foreach ($found as $file => $lines) {
        if (count($lines) > (NATIVE_IO_CALL_ALLOWLIST[$file] ?? 0)) {
            $newOffenders[] = $file.':'.implode(',', $lines);
        }
    }

    expect($newOffenders)->toBe([], implode("\n", [
        '',
        'Plain PHP file/process/time call(s) — use File, Process, Http, Sleep, now() or Str::uuid7() (.ai/rules/laravel-first.md):',
        ...array_map(fn (string $o): string => "  - {$o}", $newOffenders),
        '',
        'Or, where Laravel has no equivalent (a stream, a partial read), add the file to NATIVE_IO_CALL_ALLOWLIST in this file.',
    ]));

    // Drift down: an allowlisted call is gone, so the list must shrink too.
    $nowClean = [];

    foreach (NATIVE_IO_CALL_ALLOWLIST as $file => $allowed) {
        if (count($found[$file] ?? []) < $allowed) {
            $nowClean[] = $file;
        }
    }

    expect($nowClean)->toBe([], implode("\n", [
        '',
        'Allowlisted file has fewer plain file/process/time calls — lower or remove its NATIVE_IO_CALL_ALLOWLIST entry:',
        ...array_map(fn (string $f): string => "  - {$f}", $nowClean),
    ]));
});

/**
 * Strings and URLs go through Laravel (.ai/rules/laravel-first.md): Str for
 * string calls, Uri, SafeUrl and Arr::query() for URLs. Scanned: every PHP
 * file the rule covers. Listed: byte-level files, which must work on raw
 * bytes where Str counts characters, with how many calls each may keep.
 *
 * Not checked here: strlen(), substr(), explode() and preg_*(). They stay
 * native in many places on purpose (byte caps, offsets from
 * PREG_OFFSET_CAPTURE, several capture groups), so a count would only pin
 * noise.
 */
const NATIVE_STRING_CALL_ALLOWLIST = [
    // Binary Office, RTF and PDF readers: names, keywords and padding in raw bytes.
    'src/Domain/Knowledge/Document/Conversion/Markdown/CompoundFile.php' => 4,
    'src/Domain/Knowledge/Document/Conversion/Markdown/DocReader.php' => 3,
    'src/Domain/Knowledge/Document/Conversion/Markdown/PptReader.php' => 2,
    'src/Domain/Knowledge/Document/Conversion/Markdown/RtfReader.php' => 10,
    'src/Domain/Knowledge/Document/Conversion/PdfEncryption.php' => 5,
    'src/Domain/Knowledge/Document/Conversion/PdfSyntax.php' => 7,
    // Byte offsets into a streamed buffer, paired with substr().
    'src/Domain/Analytics/Services/Chat/AnalyticsChatRunner.php' => 1,
    'src/Domain/Conversation/Services/Streaming/Transformers/OpenAiCompatibleTransformer.php' => 2,
    'src/Domain/Llm/Providers/Messages/MessagesStream.php' => 1,
    'src/Domain/Llm/Providers/Responses/ResponsesStream.php' => 1,
    // Splits a signed token at byte offsets; the HMAC covers raw bytes.
    'src/App/Identity/Workspace/Services/AgentSignInTokenService.php' => 2,
    // Counts lines up to a byte offset from PREG_OFFSET_CAPTURE.
    'tests/Architecture/ConventionsTest.php' => 1,
    // Builds binary OLE files: padding counts bytes.
    'tests/Support/Knowledge/Document/OldOffice/CompoundFileWriter.php' => 4,
    'tests/Support/Knowledge/Document/OldOffice/PowerPointBinaryFile.php' => 1,
    'tests/Support/Knowledge/Document/OldOffice/WordBinaryFile.php' => 3,
];

/** The plain PHP string and URL functions that Str, Uri and Arr::query() replace. */
const NATIVE_STRING_FUNCTIONS = [
    'str_contains', 'str_starts_with', 'str_ends_with', 'str_replace', 'str_ireplace',
    'str_repeat', 'str_pad', 'mb_str_pad', 'str_word_count', 'substr_count',
    'strtolower', 'strtoupper', 'mb_strtolower', 'mb_strtoupper', 'ucfirst', 'lcfirst', 'ucwords',
    'trim', 'ltrim', 'rtrim', 'mb_substr', 'mb_strlen', 'strip_tags',
    'strpos', 'stripos', 'strrpos',
    'parse_url', 'http_build_query',
];

it('strings and URLs go through Str and Uri, not plain PHP functions', function (): void {
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $found = [];

    foreach (['src', 'tests', 'config', 'routes', 'database', 'bootstrap'] as $dir) {
        foreach (conventionPhpFiles($dir) as $path) {
            if (Str::contains($path, DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $lines = conventionNativeCalls((string) file_get_contents($path), NATIVE_STRING_FUNCTIONS);

            if ($lines !== []) {
                $found[Str::replace($root, '', $path)] = $lines;
            }
        }
    }

    ksort($found);

    // Drift up: a new call, or one more in an allowlisted file.
    $newOffenders = [];

    foreach ($found as $file => $lines) {
        if (count($lines) > (NATIVE_STRING_CALL_ALLOWLIST[$file] ?? 0)) {
            $newOffenders[] = $file.':'.implode(',', $lines);
        }
    }

    expect($newOffenders)->toBe([], implode("\n", [
        '',
        'Plain PHP string/URL call(s) — use Str, Uri, SafeUrl::parse() or Arr::query() (.ai/rules/laravel-first.md):',
        ...array_map(fn (string $o): string => "  - {$o}", $newOffenders),
        '',
        'Or, for code that must work on raw bytes, add the file to NATIVE_STRING_CALL_ALLOWLIST in this file.',
    ]));

    // Drift down: an allowlisted call is gone, so the list must shrink too.
    $nowClean = [];

    foreach (NATIVE_STRING_CALL_ALLOWLIST as $file => $allowed) {
        if (count($found[$file] ?? []) < $allowed) {
            $nowClean[] = $file;
        }
    }

    expect($nowClean)->toBe([], implode("\n", [
        '',
        'Allowlisted file has fewer plain string/URL calls — lower or remove its NATIVE_STRING_CALL_ALLOWLIST entry:',
        ...array_map(fn (string $f): string => "  - {$f}", $nowClean),
    ]));
});

/**
 * Line numbers in $code of a class, interface, trait or enum constant with no
 * type: `const` followed straight by the name and `=`. A constant outside any
 * braces (a Pest file's `const`) can't take a type in PHP, so it is skipped.
 *
 * @return list<int>
 */
function conventionUntypedConstants(string $code): array
{
    $tokens = array_values(array_filter(
        PhpToken::tokenize($code),
        fn (PhpToken $token): bool => ! $token->isIgnorable(),
    ));
    $depth = 0;
    $lines = [];

    foreach ($tokens as $i => $token) {
        if (in_array($token->text, ['{', '${'], true)) {
            $depth++;
        } elseif ($token->text === '}') {
            $depth--;
        }

        if ($depth > 0 && $token->is(T_CONST) && ($tokens[$i + 2] ?? null)?->text === '=') {
            $lines[] = $token->line;
        }
    }

    return $lines;
}

it('class constants declare their type', function (): void {
    // Rector types only private constants and those of final classes, so a
    // public constant on an open class (a model, a service) would slip through.
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $offenders = [];

    foreach ([...conventionPhpFiles('src'), ...conventionPhpFiles('database')] as $path) {
        foreach (conventionUntypedConstants((string) file_get_contents($path)) as $line) {
            $offenders[] = Str::replace($root, '', $path).':'.$line;
        }
    }

    expect($offenders)->toBe([], implode("\n", [
        '',
        'Untyped constant(s): write `public const string NAME = …;` (.ai/rules/src.md):',
        ...array_map(fn (string $o): string => "  - {$o}", $offenders),
    ]));
});

// --- Tests ---------------------------------------------------------------

/** @return list<string> absolute paths of every test file in the Pest suites. */
function conventionTestFiles(): array
{
    $files = [];

    foreach (['tests/Unit', 'tests/Feature', 'tests/Architecture', 'tests/Eval'] as $dir) {
        foreach (conventionPhpFiles($dir) as $path) {
            if (Str::endsWith($path, 'Test.php')) {
                $files[] = $path;
            }
        }
    }

    sort($files);

    return $files;
}

/** Whether the first thing in $code after the opening tag, `declare`, `namespace` and `use` lines is a block comment. */
function conventionStartsWithDocblock(string $code): bool
{
    $tokens = array_values(array_filter(
        PhpToken::tokenize($code),
        fn (PhpToken $token): bool => ! $token->is([T_OPEN_TAG, T_WHITESPACE]),
    ));

    for ($index = 0; isset($tokens[$index]); $index++) {
        if (! $tokens[$index]->is([T_DECLARE, T_NAMESPACE, T_USE])) {
            return $tokens[$index]->is(T_DOC_COMMENT)
                || ($tokens[$index]->is(T_COMMENT) && Str::startsWith($tokens[$index]->text, '/*'));
        }

        while (isset($tokens[$index]) && $tokens[$index]->text !== ';') {
            $index++;
        }
    }

    return false;
}

it('starts every test file with a docblock that says what it covers', function (): void {
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $offenders = [];

    foreach (conventionTestFiles() as $path) {
        if (! conventionStartsWithDocblock((string) file_get_contents($path))) {
            $offenders[] = Str::replace($root, '', $path);
        }
    }

    expect($offenders)->toBe([]);
});

it('writes tests with it(), never test()', function (): void {
    // test() with no arguments hands back the running test case; that use is fine.
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $offenders = [];

    foreach (conventionTestFiles() as $path) {
        $tokens = array_values(array_filter(
            PhpToken::tokenize((string) file_get_contents($path)),
            fn (PhpToken $token): bool => ! $token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
        ));

        foreach ($tokens as $index => $token) {
            if ($token->is(T_STRING) && $token->text === 'test'
                && ($tokens[$index + 1]->text ?? '') === '(' && ($tokens[$index + 2]->text ?? '') !== ')'
                && ! ($tokens[$index - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION])) {
                $offenders[] = Str::replace($root, '', $path).':'.$token->line;
            }
        }
    }

    expect($offenders)->toBe([]);
});

// --- Event wiring --------------------------------------------------------

it('every listener is registered in a service provider', function (): void {
    // This app has no event auto-discovery, so an unregistered listener can be
    // correct and unit-tested yet never fire. Every Listeners/ class must be
    // passed to Event::listen() (or Event::subscribe()) in a provider.
    $providerContent = implode("\n", array_map(
        fn (string $path): string => (string) file_get_contents($path),
        conventionPhpFiles('src/App/Providers'),
    ));

    $listeners = conventionFilesInSegment('Listeners');
    expect($listeners)->not->toBe([]);

    // A use line or a comment naming the listener is not a registration.
    $unregistered = [];
    foreach ($listeners as $path) {
        $shortName = basename($path, '.php');
        if (! Str::isMatch('/Event::(?:listen|subscribe)\([^;]*\b'.preg_quote($shortName, '/').'::class/', $providerContent)) {
            $unregistered[] = $shortName;
        }
    }

    sort($unregistered);

    expect($unregistered)->toBe([]);
});
