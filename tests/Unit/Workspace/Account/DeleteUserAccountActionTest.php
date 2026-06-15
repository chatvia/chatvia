<?php

use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Lead\Models\Lead;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // The teardown wipes users/{external_id} on both the default and public
    // disks; fake both so the assertions stay hermetic and never hit real S3.
    Storage::fake();
    Storage::fake('public');
});

it('hard-deletes the user row', function (): void {
    $user = User::factory()->create();

    app(DeleteUserAccountAction::class)->execute($user);

    expect(User::whereKey($user->id)->exists())->toBeFalse();
});

it('does not touch unrelated users', function (): void {
    $target = User::factory()->create();
    $bystander = User::factory()->create();

    app(DeleteUserAccountAction::class)->execute($target);

    expect(User::whereKey($bystander->id)->exists())->toBeTrue();
});

it('tears down team memberships, owned teams, and workspace sub-users', function (): void {
    $owner = User::factory()->create();
    // Auto-provisioning is retired (the trial-checkout webhook owns it), so
    // seed the default team explicitly.
    $team = app(TeamService::class)->createDefaultTeam($owner);
    $role = $team->roles()->firstOrFail();

    // The owner is a member of their own team — this team_user row is exactly
    // what the team_user.team_id RESTRICT FK trips on if not cleared first.
    $team->members()->attach($owner->id, ['role_id' => $role->id]);

    $employee = User::factory()->employee($owner)->create();
    $team->members()->attach($employee->id, ['role_id' => $role->id]);

    $chatter = User::factory()->chatter($owner)->create();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($owner->id)->exists())->toBeFalse()
        ->and(User::whereKey($employee->id)->exists())->toBeFalse()
        ->and(User::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(Team::whereKey($team->id)->exists())->toBeFalse()
        ->and(DB::table('team_user')->where('team_id', $team->id)->exists())->toBeFalse();
});

it('tears down a workspace with multiple already-canceled subscriptions without calling Stripe', function (): void {
    $owner = User::factory()->create();

    // Factory users carry no UserBilling row (the trial-checkout webhook
    // provisions it in production) — create it explicitly.
    $billing = $owner->billing()->firstOrCreate([]);

    // Already-canceled subscriptions are skipped (no Stripe call) but must still
    // cascade-delete with user_billing — and a workspace can hold more than one.
    foreach (['sub_test_a', 'sub_test_b'] as $stripeId) {
        $billing->subscriptions()->create([
            'type' => $stripeId,
            'stripe_id' => $stripeId,
            'stripe_status' => 'canceled',
            'ends_at' => now()->subDay(),
        ]);
    }

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($owner->id)->exists())->toBeFalse()
        ->and(DB::table('subscriptions')->whereIn('stripe_id', ['sub_test_a', 'sub_test_b'])->exists())->toBeFalse();
});

it('deletes a workspace whose subscription is canceled but still within its trial window', function (): void {
    // Regression for the account-deletion 500: a subscription Stripe has already
    // canceled still reports subscribed()/onTrial() === true locally while
    // trial_ends_at is in the future. The teardown must NOT cancel it again —
    // Stripe rejects re-cancelling a closed subscription ("A canceled subscription
    // can only update its cancellation_details and metadata"). cancelSubscriptions()
    // skips it (ends_at is set) and nothing else may reach for Stripe; if anything
    // does, it hits $owner->stripe() and this test blows up. A clean delete proves
    // no second cancel is attempted.
    $owner = User::factory()->create();

    $billing = $owner->billing()->firstOrCreate([]);
    $billing->stripe_id = 'cus_test_trial';
    $billing->save();

    $subscription = $billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_trial',
        'stripe_status' => 'canceled',
        'ends_at' => now()->subHour(),        // already closed on Stripe
        'trial_ends_at' => now()->addDays(2), // …but the trial window hasn't lapsed
    ]);

    // The precondition that used to trip UserObserver::deleting(): canceled, yet
    // still "subscribed" because the trial is live.
    expect($subscription->canceled())->toBeTrue()
        ->and($owner->billing->subscribed())->toBeTrue();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($owner->id)->exists())->toBeFalse()
        ->and(DB::table('subscriptions')->where('id', $subscription->id)->exists())->toBeFalse();
});

it('leaves other workspaces untouched while tearing one down', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $otherEmployee = User::factory()->employee($otherOwner)->create();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($otherOwner->id)->exists())->toBeTrue()
        ->and(User::whereKey($otherEmployee->id)->exists())->toBeTrue();
});

it('wipes conversation attachment files that the FK cascade would otherwise orphan on S3', function (): void {
    // chat_attachments.chat_conversation_id / .chat_message_id are both
    // cascadeOnDelete, so the message batch-delete + user cascade drop the
    // rows WITHOUT firing the Attachment deleting-observer that wipes S3.
    // The teardown must delete them through Eloquent first.
    Storage::fake('public');

    $agent = Agent::factory()->create();
    $workspace = User::findOrFail($agent->getUserId());

    $session = ChatSession::create(['agent_id' => $agent->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'With attachment',
    ]);

    $filepath = "users/{$workspace->external_id}/agents/{$agent->external_id}/attachments/pending/doc.pdf";
    Storage::disk('public')->put($filepath, 'data');
    $attachment = Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'filepath' => $filepath,
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 4,
    ]);

    Storage::disk('public')->assertExists($filepath);

    app(DeleteUserAccountAction::class)->execute($workspace);

    Storage::disk('public')->assertMissing($filepath);
    expect(Attachment::whereKey($attachment->id)->exists())->toBeFalse();
});

it('wipes the workspace knowledge-file storage tree the FK cascade would orphan', function (): void {
    // Agents are removed by FK cascade, which never fires AgentObserver — so the
    // knowledge files under users/{external_id}/agents/{external_id}/ would leak.
    // The teardown wipes the whole users/{external_id} tree to catch them.
    $agent = Agent::factory()->create();
    $workspace = User::findOrFail($agent->getUserId());

    $knowledgePath = 'users/'.$workspace->external_id.'/agents/'.$agent->external_id.'/files/doc.pdf';
    Storage::put($knowledgePath, 'pdf-bytes');
    Storage::assertExists($knowledgePath);

    app(DeleteUserAccountAction::class)->execute($workspace);

    Storage::assertMissing($knowledgePath);
});

it('flushes the deleted workspace chat memory cache and leaves other workspaces intact', function (): void {
    // The chat cache is Redis in config; point it at the array driver so the
    // tagged flush is exercised without a live Redis (mirrors the memory unit
    // tests). Every chat-memory entry carries ChatCache::workspaceTag(), so a
    // single tag flush must clear the deleted workspace and nothing else.
    config(['cache.stores.chat' => ['driver' => 'array']]);

    $agent = Agent::factory()->create();
    $workspace = User::findOrFail($agent->getUserId());
    $workspaceTag = ChatCache::workspaceTag($workspace->external_id);

    $survivor = User::factory()->create();
    $survivorTag = ChatCache::workspaceTag($survivor->external_id);

    Cache::driver('chat')->tags([$workspaceTag, 'session-a'])->forever('session-a', ['conversations' => []]);
    Cache::driver('chat')->tags([$survivorTag, 'session-b'])->forever('session-b', ['conversations' => []]);

    app(DeleteUserAccountAction::class)->execute($workspace);

    expect(Cache::driver('chat')->tags([$workspaceTag, 'session-a'])->has('session-a'))->toBeFalse()
        ->and(Cache::driver('chat')->tags([$survivorTag, 'session-b'])->has('session-b'))->toBeTrue();
});

it('deletes leads, Sanctum tokens and HTTP sessions the FK cascade would orphan', function (): void {
    $owner = User::factory()->create();
    $employee = User::factory()->employee($owner)->create();
    $agent = Agent::factory()->create(['user_id' => $owner->id]);

    $lead = Lead::factory()->forAgent($agent)->create();

    $owner->createToken('owner-cli');
    $employee->createToken('emp-cli');

    foreach ([$owner->id, $employee->id] as $i => $uid) {
        DB::table('sessions')->insert([
            'id' => "sess-ws-{$i}",
            'user_id' => $uid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);
    }

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(Lead::whereKey($lead->id)->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->whereIn('tokenable_id', [$owner->id, $employee->id])->where('tokenable_type', $owner->getMorphClass())->exists())->toBeFalse()
        ->and(DB::table('sessions')->whereIn('user_id', [$owner->id, $employee->id])->exists())->toBeFalse();
});
