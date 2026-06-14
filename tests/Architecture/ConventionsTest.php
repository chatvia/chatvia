<?php

use Domain\Widget\Services\WidgetVerification;

/*
 * Executable form of the "Conventions enforcement (grep checks)" section of
 * CLAUDE.md. Previously these were manual greps a reviewer had to remember to
 * run; here they fail CI instead, so the layering can't silently erode.
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
    // Sanctioned HTTP→domain boundary adapters (see CLAUDE.md).
    $allowed = [
        'src/Domain/Widget/Services/WidgetVerification.php',
        'src/Domain/Analytics/Data/DateRangeData.php',
        'src/Domain/Conversation/Services/Streaming/Requests/StreamRequestDataFactory.php',
    ];

    $offenders = array_values(array_diff(
        conventionOffenders(conventionPhpFiles('src/Domain'), '/use Illuminate\\\\Http\\\\Request;/'),
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
    // CLAUDE.md: "Jobs hold no logic. Receive a DTO in the constructor, call an
    // Action's execute() in handle()." A DB::transaction / lockForUpdate inside
    // a Job is the tell-tale of business logic that belongs in an Action (the
    // UpdateUserCreditsJob → ConsumeMessageCreditsAction extraction). Guarding
    // it here stops the pattern from being copied into the next job.
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
 * Pure read-only `index`/`show` are not in scope — CLAUDE.md exempts those
 * from the Action layer and `$request->validate` is allowed there. We catch
 * everything else by globbing the controllers tree.
 */
const INLINE_VALIDATE_ALLOWLIST = [
    'src/App/Http/Controllers/Agent/Analytics/Overview/AgentAnalyticsDataController.php',
    'src/App/Http/Controllers/Agent/Analytics/Topic/DismissSuggestedTopicController.php',
    'src/App/Http/Controllers/Agent/Analytics/Topic/PromoteSuggestedTopicController.php',
    'src/App/Http/Controllers/Agent/ChatLog/AgentChatLogReviseAnswerController.php',
    'src/App/Http/Controllers/Agent/Lead/AgentLeadController.php',
    'src/App/Http/Controllers/Agent/Lead/AgentLeadSettingsController.php',
    'src/App/Http/Controllers/Agent/Lead/AgentLeadsExportController.php',
    'src/App/Http/Controllers/Agent/Security/AgentSecurityUpdateController.php',
    'src/App/Http/Controllers/Agent/Settings/AgentSettingsController.php',
    'src/App/Http/Controllers/Agent/Source/File/Manage/AgentSourceFileController.php',
    'src/App/Http/Controllers/Agent/Source/File/Manage/AgentTrainedFileController.php',
    'src/App/Http/Controllers/Agent/Source/File/Upload/AgentKnowledgeFilesAnalyzeController.php',
    'src/App/Http/Controllers/Agent/Source/Web/AgentTrainedWebsiteController.php',
    'src/App/Http/Controllers/Agent/Source/Web/BulkDestroyAgentWebsiteLinksController.php',
    'src/App/Http/Controllers/Agent/Source/Web/TrainAgentWebsiteController.php',
    'src/App/Http/Controllers/Agent/Source/Youtube/AgentYoutubeController.php',
    'src/App/Http/Controllers/Agent/Starters/AgentStarterController.php',
    'src/App/Http/Controllers/Api/V1/Team/RoleMemberController.php',
    'src/App/Http/Controllers/Auth/ForgotPasswordController.php',
    'src/App/Http/Controllers/Chat/Messages/MessageDeleteController.php',
    'src/App/Http/Controllers/Chat/Messages/MessageFeedbackController.php',
    'src/App/Http/Controllers/Settings/Connector/Catalog/ConnectController.php',
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
        'Use a FormRequest with toData() instead (see CLAUDE.md FormRequests section)',
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

// --- Multi-tenant lookup discipline -------------------------------------

/**
 * Inline Conversation::where('external_id', …) and Session::where('external_id', …)
 * lookups skip the agent-scoping that {@see WidgetVerification}
 * exists to centralise. Some legacy call sites enforce ownership via middleware
 * or a separate whereHas — those are listed below — but a NEW callsite must go
 * through WidgetVerification so the cross-tenant defence is grep-able in one place.
 */
const EXTERNAL_ID_LOOKUP_ALLOWLIST = [
    // ConversationDeleteController + ConversationUpdate now scope the lookup to
    // the caller's session + agent via whereHas('session'), so they no longer
    // trip the bare-inline-lookup detector and are removed from this allowlist.
    'src/App/Http/Controllers/Chat/Conversations/GenerateConversationTitleController.php',
    'src/App/Http/Controllers/Widget/Activity/HeartbeatController.php',
    'src/Domain/Conversation/Services/ChatStreamService.php',
    'src/Domain/Conversation/Services/Memory/ConversationManager.php',
    // WidgetVerification *is* the canonical lookup site — exempt.
    'src/Domain/Widget/Services/WidgetVerification.php',
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
