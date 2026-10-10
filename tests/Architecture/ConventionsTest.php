<?php

use Domain\Widget\Services\WidgetVerification;

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
        if ($file->isFile() && str_ends_with((string) $file, '.php')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/** Files whose path contains /<segment>/, across Domain + the Identity carve-out. */
function conventionFilesInSegment(string $segment): array
{
    $needle = DIRECTORY_SEPARATOR.$segment.DIRECTORY_SEPARATOR;

    return array_values(array_filter(
        [...conventionPhpFiles('src/Domain'), ...conventionPhpFiles('src/App/Identity')],
        fn (string $path): bool => str_contains($path, $needle),
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
        if (preg_match($pattern, (string) file_get_contents($path)) === 1) {
            $offenders[] = str_replace($root, '', $path);
        }
    }

    sort($offenders);

    return $offenders;
}

// --- Dependency direction ------------------------------------------------

it('the Domain layer does not import the HTTP request object', function (): void {
    // Sanctioned HTTP→domain boundary adapters (see ARCHITECTURE.md).
    $allowed = [
        'src/Domain/Widget/Services/WidgetVerification.php',
        'src/Domain/Conversation/Services/Streaming/Requests/StreamRequestDataFactory.php',
    ];

    // Aliased imports (`use Illuminate\Http\Request as HttpRequest;`) count too.
    $offenders = array_values(array_diff(
        conventionOffenders(conventionPhpFiles('src/Domain'), '/use Illuminate\\\\Http\\\\Request( as \w+)?;/'),
        $allowed,
    ));

    expect($offenders)->toBe([]);
});

it('the Domain layer never imports from App except the Identity carve-out', function (): void {
    $root = realpath(__DIR__.'/../../').DIRECTORY_SEPARATOR;
    $offenders = [];

    foreach (conventionPhpFiles('src/Domain') as $path) {
        if (preg_match('/^use App\\\\(?!Identity\\\\)/m', (string) file_get_contents($path)) === 1) {
            $offenders[] = str_replace($root, '', $path);
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
            && preg_match('/^\\\\?(Domain|App)\\\\/', $token->text) === 1;

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
            $offenders[] = str_replace($root, '', $path).' → '.$reference;
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
        fn (string $path): bool => str_contains($path, DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR),
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
        if (str_contains($path, DIRECTORY_SEPARATOR.'Concerns'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        preg_match_all('/^\s*public\s+(?:static\s+)?function\s+(\w+)\s*\(/m', (string) file_get_contents($path), $matches);

        foreach (array_diff($matches[1], CONTROLLER_PUBLIC_METHODS) as $method) {
            $offenders[] = str_replace($root, '', $path).'::'.$method.'()';
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
 * exists to centralise. Some legacy call sites enforce ownership via middleware
 * or a separate whereHas — those are listed below — but a NEW callsite must go
 * through WidgetVerification so the cross-tenant defence is grep-able in one place.
 */
const EXTERNAL_ID_LOOKUP_ALLOWLIST = [
    // Empty: every lookup by external id is scoped to the caller's session or
    // agent (see ConversationQueryBuilder::inSession and WidgetVerification).
];

it('Conversation/Session external_id lookups go through WidgetVerification', function (): void {
    $sources = [...conventionPhpFiles('src/Domain'), ...conventionPhpFiles('src/App/Http/Controllers')];

    $offenders = conventionOffenders(
        $sources,
        '/(?:Conversation|Session)::(?:where|query\(\)->where)\([\'"]external_id[\'"]/',
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

// --- Event wiring --------------------------------------------------------

it('every listener is registered in a service provider', function (): void {
    // This app has no event auto-discovery, so an unregistered listener can be
    // correct and unit-tested yet silently never fire (the SendTeamInvitationEmail
    // bug). Every Listeners/ class must appear in a provider's registration.
    $providerContent = implode("\n", array_map(
        fn (string $path): string => (string) file_get_contents($path),
        conventionPhpFiles('src/App/Providers'),
    ));

    $listeners = conventionFilesInSegment('Listeners');
    expect($listeners)->not->toBe([]);

    $unregistered = [];
    foreach ($listeners as $path) {
        $shortName = basename($path, '.php');
        if (! str_contains($providerContent, $shortName)) {
            $unregistered[] = $shortName;
        }
    }

    sort($unregistered);

    expect($unregistered)->toBe([]);
});
