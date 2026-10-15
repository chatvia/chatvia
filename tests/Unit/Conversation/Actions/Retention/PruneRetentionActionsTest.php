<?php

use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Actions\Retention\PruneAgentActivityAction;
use Domain\Conversation\Actions\Retention\PruneExpiredConversationsAction;
use Domain\Conversation\Actions\Retention\PruneToolApprovalCheckpointsAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function retentionConversation(array $attributes = []): Conversation
{
    $agent = Agent::factory()->create();

    return Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $agent->id])->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::Widget,
        ...$attributes,
    ]);
}

function retentionUpload(Conversation $conversation): Attachment
{
    $path = 'users/x/agents/y/attachments/'.Str::uuid().'.pdf';
    Storage::disk(Attachment::DISK)->put($path, 'pdf');

    return Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'filepath' => $path,
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 3,
    ]);
}

function retentionCheckpoint(ToolApprovalStatus $status, ?CarbonInterface $expiresAt, int $daysSinceUpdate = 0): ToolApprovalCheckpoint
{
    $checkpoint = ToolApprovalCheckpoint::create([
        'agent_id' => Agent::factory()->create()->id,
        'conversation_external_id' => (string) Str::uuid(),
        'assistant_message_id' => (string) Str::uuid(),
        'iteration' => 0,
        'status' => $status,
        'messages_snapshot' => [],
        'pending_calls' => [],
        'context' => [],
        'expires_at' => $expiresAt,
    ]);

    DB::table('tool_approval_checkpoints')->where('id', $checkpoint->id)->update(['updated_at' => now()->subDays($daysSinceUpdate)]);

    return $checkpoint;
}

it('soft-deletes conversations past their horizon and purges them after the grace window', function (): void {
    $expired = retentionConversation(['expires_at' => now()->subDay()]);
    $live = retentionConversation(['expires_at' => now()->addDay()]);
    $longGone = retentionConversation();
    $longGone->delete();
    DB::table('chat_conversations')->where('id', $longGone->id)->update(['deleted_at' => now()->subDays(40)]);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect($result->expired)->toBe(1)
        ->and($result->purged)->toBe(1)
        ->and(Conversation::withTrashed()->find($expired->id)->trashed())->toBeTrue()
        ->and($live->fresh()->trashed())->toBeFalse()
        ->and(Conversation::withTrashed()->find($longGone->id))->toBeNull();
});

it('soft-deletes and purges in small batches', function (): void {
    $expired = collect(range(1, 5))->map(fn (): Conversation => retentionConversation(['expires_at' => now()->subDay()]));
    $trashed = collect(range(1, 5))->map(function (): Conversation {
        $conversation = retentionConversation();
        $conversation->delete();
        DB::table('chat_conversations')->where('id', $conversation->id)->update(['deleted_at' => now()->subDays(40)]);

        return $conversation;
    });

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 2, graceDays: 30);

    expect($result->expired)->toBe(5)
        ->and($result->purged)->toBe(5)
        ->and(Conversation::onlyTrashed()->whereKey($expired->pluck('id'))->count())->toBe(5)
        ->and(Conversation::withTrashed()->whereKey($trashed->pluck('id'))->exists())->toBeFalse();
});

it('pulls horizons beyond the current retention back to it, in batches', function (): void {
    config(['chatvia.retention.conversations_days' => 30]);
    $activeAt = now()->subDays(10)->startOfSecond();
    $insideHorizon = now()->addDays(10)->startOfSecond();
    $farHorizon = now()->addDays(300)->startOfSecond();

    // Stamped under a longer retention, still active: horizon becomes updated_at + 30 days.
    $active = retentionConversation(['expires_at' => $farHorizon]);
    DB::table('chat_conversations')->where('id', $active->id)->update(['updated_at' => $activeAt]);
    // Idle longer than the new retention: clamped into the past and expired in the same run.
    $idle = retentionConversation(['expires_at' => $farHorizon]);
    DB::table('chat_conversations')->where('id', $idle->id)->update(['updated_at' => now()->subDays(40)]);
    // Already inside the new retention: untouched.
    $inside = retentionConversation(['expires_at' => $insideHorizon]);
    // Already in the trash: untouched.
    $trashed = retentionConversation(['expires_at' => $farHorizon]);
    DB::table('chat_conversations')->where('id', $trashed->id)->update(['deleted_at' => now()]);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 1, graceDays: 30);

    expect($result->clamped)->toBeGreaterThanOrEqual(2)
        ->and($active->fresh()->expires_at->equalTo($activeAt->copy()->addDays(30)))->toBeTrue()
        ->and($active->fresh()->trashed())->toBeFalse()
        ->and(Conversation::withTrashed()->find($idle->id)->trashed())->toBeTrue()
        ->and($inside->fresh()->expires_at->equalTo($insideHorizon))->toBeTrue()
        ->and(Conversation::withTrashed()->find($trashed->id)->expires_at->equalTo($farHorizon))->toBeTrue();
});

it('sweeps a hidden conversation once its short life is over', function (): void {
    $agent = Agent::factory()->create();
    $id = app(StartConversationAction::class)->execute(new ChatScopeData($agent, (string) Str::uuid()), ConversationSource::Widget);
    $conversation = Conversation::query()->where('external_id', $id)->sole();

    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);
    expect($conversation->fresh()->trashed())->toBeFalse();

    $this->travel(Conversation::HIDDEN_LIFETIME_DAYS)->days();
    $this->travel(1)->minutes();
    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect(Conversation::withTrashed()->find($conversation->id)->trashed())->toBeTrue();
});

it('removes the files of a conversation it expires', function (): void {
    Storage::fake(Attachment::DISK);
    $conversation = retentionConversation(['expires_at' => now()->subDay()]);
    $upload = retentionUpload($conversation);

    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($upload->filepath);
});

it('removes a file a bulk soft delete left behind when it purges the conversation', function (): void {
    Storage::fake(Attachment::DISK);
    $conversation = retentionConversation();
    $upload = retentionUpload($conversation);
    // A bulk soft-delete skips the model hook that would have removed the file.
    DB::table('chat_conversations')->where('id', $conversation->id)->update(['deleted_at' => now()->subDays(40)]);

    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect(Conversation::withTrashed()->find($conversation->id))->toBeNull()
        ->and(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($upload->filepath);
});

it('removes the anonymous sessions the purge left empty, and only those', function (): void {
    $agent = Agent::factory()->create();
    $trash = function (Session $session): Conversation {
        $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $session->agent_id]);
        DB::table('chat_conversations')->where('id', $conversation->id)->update(['deleted_at' => now()->subDays(40)]);

        return $conversation;
    };

    $emptied = Session::factory()->create(['agent_id' => $agent->id]);
    $trash($emptied);
    $trash($emptied);

    $stillUsed = Session::factory()->create(['agent_id' => $agent->id]);
    $trash($stillUsed);
    Conversation::factory()->create(['chat_session_id' => $stillUsed->id, 'agent_id' => $agent->id]);

    $chatters = Session::factory()->forChatter()->create(['agent_id' => $agent->id]);
    $trash($chatters);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 1, graceDays: 30);

    expect($result->purged)->toBe(4)
        ->and($result->orphanSessions)->toBeGreaterThanOrEqual(1)
        ->and(Session::query()->find($emptied->id))->toBeNull()
        ->and(Session::query()->find($stillUsed->id))->not->toBeNull()
        ->and(Session::query()->find($chatters->id))->not->toBeNull();
});

it('keeps a never-used anonymous session until it is old', function (): void {
    $agent = Agent::factory()->create();
    $fresh = Session::factory()->create(['agent_id' => $agent->id]);

    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect(Session::query()->find($fresh->id))->not->toBeNull();
});

it('removes anonymous sessions left empty for a long time', function (): void {
    $agent = Agent::factory()->create();
    $empty = Session::factory()->create(['agent_id' => $agent->id, 'user_id' => null]);
    DB::table('chat_sessions')->where('id', $empty->id)->update(['created_at' => now()->subDays(PruneExpiredConversationsAction::ORPHAN_SESSION_DAYS + 1)]);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect($result->orphanSessions)->toBe(1)
        ->and(Session::query()->find($empty->id))->toBeNull();
});

it('keeps an empty session that belongs to a chatter', function (): void {
    $agent = Agent::factory()->create();
    $chatterSession = Session::factory()->forChatter()->create(['agent_id' => $agent->id]);
    DB::table('chat_sessions')->where('id', $chatterSession->id)->update(['created_at' => now()->subDays(PruneExpiredConversationsAction::ORPHAN_SESSION_DAYS + 1)]);

    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect(Session::query()->find($chatterSession->id))->not->toBeNull();
});

it('deletes checkpoints in batches', function (): void {
    $stale = collect(range(1, 3))->map(fn (): ToolApprovalCheckpoint => retentionCheckpoint(ToolApprovalStatus::Pending, now()->subMinute()));

    expect(app(PruneToolApprovalCheckpointsAction::class)->execute(graceDays: 3, batchSize: 1))->toBeGreaterThanOrEqual(3)
        ->and(ToolApprovalCheckpoint::query()->whereKey($stale->pluck('id'))->exists())->toBeFalse();
});

it('deletes expired checkpoints and resolved ones past the grace period', function (): void {
    $pending = retentionCheckpoint(ToolApprovalStatus::Pending, now()->addHour());
    $expired = retentionCheckpoint(ToolApprovalStatus::Pending, now()->subMinute());
    $recentlyResolved = retentionCheckpoint(ToolApprovalStatus::Resolved, now()->addHour(), daysSinceUpdate: 1);
    $oldResolved = retentionCheckpoint(ToolApprovalStatus::Resolved, now()->addHour(), daysSinceUpdate: 5);

    expect(app(PruneToolApprovalCheckpointsAction::class)->execute(graceDays: 3))->toBe(2)
        ->and(ToolApprovalCheckpoint::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$pending->id, $recentlyResolved->id])->sort()->values()->all());
});

it('deletes audit rows older than the retention window', function (): void {
    $agent = Agent::factory()->create();
    $row = fn (int $daysOld): AgentActivity => AgentActivity::create([
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'workspace_id' => $agent->workspace_id,
        'trace_id' => (string) Str::uuid(),
        'tool_call_id' => 'call_'.Str::random(6),
        'tool_name' => 'get_weather',
        'tool_kind' => 'function',
        'outcome' => 'auto',
        'gated' => false,
        'is_error' => false,
        'arguments_truncated' => false,
        'result_truncated' => false,
        'created_at' => now()->subDays($daysOld),
    ]);

    $old = $row(100);
    $older = $row(200);
    $oldest = $row(300);
    $recent = $row(10);

    expect(app(PruneAgentActivityAction::class)->execute(retentionDays: 90, batchSize: 1))->toBe(3)
        ->and(AgentActivity::query()->whereKey([$old->id, $older->id, $oldest->id])->exists())->toBeFalse()
        ->and(AgentActivity::query()->find($recent->id))->not->toBeNull();
});
