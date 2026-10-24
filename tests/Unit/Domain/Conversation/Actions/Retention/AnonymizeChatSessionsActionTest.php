<?php

use App\Admin\Settings\BrandSettings;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Retention\AnonymizeChatSessionsAction;
use Domain\Conversation\Models\Session;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Visitor anonymization: sessions older than the workspace's window lose the
 * visitor's details but keep what analytics needs; other workspaces and newer
 * sessions are untouched.
 */
function acsVisitorSession(Agent $agent, int $daysAgo): Session
{
    $session = Session::factory()->withCountry()->create([
        'agent_id' => $agent->id,
        'name' => 'Dana Visitor',
        'email' => 'dana@example.com',
        'phone' => '+15550100',
        'ip_address' => '203.0.113.9',
        'is_proxy' => false,
        'user_agent' => 'Mozilla/5.0 (Macintosh)',
        'device_type' => 'desktop',
        'browser' => 'Safari',
        'os' => 'macOS',
        'city' => 'Dubai',
        'region' => 'Dubai',
        'zip_code' => '00000',
        'time_zone' => 'Asia/Dubai',
        'referrer_url' => 'https://example.com/from',
        'page_url' => 'https://example.com/pricing',
        'locale' => 'en',
    ]);

    DB::table('chat_sessions')->where('id', $session->id)->update([
        'created_at' => now()->subDays($daysAgo),
        'updated_at' => now()->subDays($daysAgo),
    ]);

    return $session;
}

function acsAnonymizeAfter(Agent $agent, ?int $days): void
{
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['anonymize_after_days' => $days]);
}

beforeEach(fn () => withEnterpriseLicense());

it('clears visitor details on that workspace\'s older sessions and keeps the analytics fields', function (): void {
    $agent = Agent::factory()->create();
    acsAnonymizeAfter($agent, 30);
    $old = acsVisitorSession($agent, 45);
    $recent = acsVisitorSession($agent, 5);

    $other = Agent::factory()->create(); // no anonymize window set
    $otherOld = acsVisitorSession($other, 400);

    expect(app(AnonymizeChatSessionsAction::class)->execute())->toBe(1);

    // Listed here rather than read from the action, so a column dropped from
    // its list fails the test.
    $visitorColumns = ['name', 'email', 'phone', 'ip_address', 'is_proxy', 'user_agent', 'city', 'region', 'zip_code', 'time_zone', 'referrer_url', 'page_url'];
    $cleared = DB::table('chat_sessions')->where('id', $old->id)->first();

    expect(collect((array) $cleared)->only($visitorColumns)->reject(fn (mixed $value): bool => $value === null)->keys()->all())->toBe([])
        ->and($cleared->anonymized_at)->not->toBeNull()
        // What analytics reads stays.
        ->and((int) $cleared->agent_id)->toBe($agent->id)
        ->and($cleared->country_id)->toEqual($old->country_id)
        ->and($cleared->device_type)->toBe('desktop')
        ->and($cleared->browser)->toBe('Safari')
        ->and($cleared->os)->toBe('macOS')
        ->and($cleared->locale)->toBe('en')
        // Clearing details is not visitor activity.
        ->and($cleared->updated_at)->toBe(DB::table('chat_sessions')->where('id', $old->id)->value('created_at'));

    expect(DB::table('chat_sessions')->whereIn('id', [$recent->id, $otherOld->id])->get(['anonymized_at', 'email', 'ip_address'])->map(fn (object $row): array => (array) $row)->all())
        ->toBe([
            ['anonymized_at' => null, 'email' => 'dana@example.com', 'ip_address' => '203.0.113.9'],
            ['anonymized_at' => null, 'email' => 'dana@example.com', 'ip_address' => '203.0.113.9'],
        ]);
});

it('anonymizes in small batches and only once', function (): void {
    $agent = Agent::factory()->create();
    acsAnonymizeAfter($agent, 7);
    collect(range(11, 15))->each(fn (int $daysAgo): Session => acsVisitorSession($agent, $daysAgo));

    $action = app(AnonymizeChatSessionsAction::class);

    expect($action->execute(batchSize: 2))->toBe(5)
        ->and($action->execute(batchSize: 2))->toBe(0)
        ->and(Session::query()->where('agent_id', $agent->id)->whereNull('anonymized_at')->count())->toBe(0);
});

it('never uses a window below the configured floor', function (): void {
    config(['chatvia.retention.anonymize_floor_days' => 30]);
    $agent = Agent::factory()->create();
    acsAnonymizeAfter($agent, 1); // saved before the floor was raised
    $session = acsVisitorSession($agent, 10);

    expect(app(AnonymizeChatSessionsAction::class)->execute())->toBe(0)
        ->and(DB::table('chat_sessions')->where('id', $session->id)->value('email'))->toBe('dana@example.com');
});

it('anonymizes nothing without an Enterprise license', function (): void {
    $agent = Agent::factory()->create();
    acsAnonymizeAfter($agent, 30);
    $old = acsVisitorSession($agent, 45);
    saveSettings(BrandSettings::class, ['license_key' => null]);

    expect(app(AnonymizeChatSessionsAction::class)->execute())->toBe(0)
        ->and($old->fresh()->email)->toBe('dana@example.com');
});
