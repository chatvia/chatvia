<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Actions\Retention\PruneExpiredConversationsAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The conversation sweep: conversations past their horizon go to the trash
 * with their live memory and files, the trash is purged after the grace
 * window, horizons stamped under a longer retention are pulled back to the
 * current one, and anonymous sessions left empty go too. Chatter sessions
 * stay.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

/** A conversation in the trash since the given number of days. */
function pecaTrashed(array $attributes = [], int $daysAgo = 40): Conversation
{
    $conversation = Conversation::factory()->create($attributes);
    DB::table('chat_conversations')->where('id', $conversation->id)->update(['deleted_at' => now()->subDays($daysAgo)]);

    return $conversation;
}

it('soft-deletes conversations past their horizon and purges them after the grace window', function (): void {
    $expired = Conversation::factory()->expiresAt(now()->subDay())->create();
    $live = Conversation::factory()->expiresAt(now()->addDay())->create();
    $longGone = pecaTrashed();

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect($result->expired)->toBe(1)
        ->and($result->purged)->toBe(1)
        ->and(Conversation::withTrashed()->find($expired->id)->trashed())->toBeTrue()
        ->and($live->fresh()->trashed())->toBeFalse()
        ->and(Conversation::withTrashed()->find($longGone->id))->toBeNull();
});

it('soft-deletes and purges in small batches', function (): void {
    $expired = Conversation::factory()->count(5)->expiresAt(now()->subDay())->create();
    $trashed = collect(range(1, 5))->map(fn (): Conversation => pecaTrashed());

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
    $active = Conversation::factory()->expiresAt($farHorizon)->create();
    DB::table('chat_conversations')->where('id', $active->id)->update(['updated_at' => $activeAt]);
    // Idle longer than the new retention: clamped into the past and expired in the same run.
    $idle = Conversation::factory()->expiresAt($farHorizon)->create();
    DB::table('chat_conversations')->where('id', $idle->id)->update(['updated_at' => now()->subDays(40)]);
    // Already inside the new retention: untouched.
    $inside = Conversation::factory()->expiresAt($insideHorizon)->create();
    // Already in the trash: untouched.
    $trashed = pecaTrashed(['expires_at' => $farHorizon], daysAgo: 0);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 1, graceDays: 30);

    expect($result->clamped)->toBe(2)
        ->and($active->fresh()->expires_at->equalTo($activeAt->copy()->addDays(30)))->toBeTrue()
        ->and($active->fresh()->trashed())->toBeFalse()
        ->and(Conversation::withTrashed()->find($idle->id)->trashed())->toBeTrue()
        ->and($inside->fresh()->expires_at->equalTo($insideHorizon))->toBeTrue()
        ->and(Conversation::withTrashed()->find($trashed->id)->expires_at->equalTo($farHorizon))->toBeTrue();
});

it('sweeps a hidden conversation once its short life is over', function (): void {
    $agent = Agent::factory()->create();
    $id = app(StartConversationAction::class)->execute(new ChatScopeData($agent, (string) Str::uuid()), ConversationSource::Widget)->external_id;
    $conversation = Conversation::query()->where('external_id', $id)->sole();

    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);
    expect($conversation->fresh()->trashed())->toBeFalse();

    $this->travel(Conversation::HIDDEN_LIFETIME_DAYS)->days();
    $this->travel(1)->minutes();
    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect(Conversation::withTrashed()->find($conversation->id)->trashed())->toBeTrue();
});

it('clears the live memory of every conversation it expires, batch by batch', function (): void {
    $this->freezeSecond();
    $expired = Conversation::factory()->count(3)->expiresAt(now()->subDay())->create();
    $live = Conversation::factory()->expiresAt(now()->addDay())->create();
    $cache = Cache::driver('chat');
    foreach ([...$expired, $live] as $conversation) {
        $cache->tags(ChatCache::conversationTag($conversation->external_id))->put('messages', 'cached');
    }

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 2, graceDays: 30);

    expect($result->expired)->toBe(3);
    foreach ($expired as $conversation) {
        $trashed = Conversation::withTrashed()->findOrFail($conversation->id);

        expect($cache->tags(ChatCache::conversationTag($conversation->external_id))->get('messages'))->toBeNull()
            ->and($trashed->trashed())->toBeTrue()
            ->and($trashed->updated_at->equalTo(now()))->toBeTrue();
    }
    expect($cache->tags(ChatCache::conversationTag($live->external_id))->get('messages'))->toBe('cached');
});

it('removes the files of a conversation it expires', function (): void {
    $conversation = Conversation::factory()->expiresAt(now()->subDay())->create();
    $upload = Attachment::factory()->forConversation($conversation)->stored()->create();

    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($upload->filepath);
});

it('removes a file a bulk soft delete left behind when it purges the conversation', function (): void {
    $conversation = Conversation::factory()->create();
    $upload = Attachment::factory()->forConversation($conversation)->stored()->create();
    // A bulk soft-delete skips the model hook that would have removed the file.
    DB::table('chat_conversations')->where('id', $conversation->id)->update(['deleted_at' => now()->subDays(40)]);

    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect(Conversation::withTrashed()->find($conversation->id))->toBeNull()
        ->and(Attachment::query()->find($upload->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($upload->filepath);
});

it('removes the anonymous sessions the purge left empty, and only those', function (): void {
    $agent = Agent::factory()->create();

    $emptied = Session::factory()->create(['agent_id' => $agent->id]);
    pecaTrashed(['chat_session_id' => $emptied->id]);
    pecaTrashed(['chat_session_id' => $emptied->id]);

    $stillUsed = Session::factory()->create(['agent_id' => $agent->id]);
    pecaTrashed(['chat_session_id' => $stillUsed->id]);
    Conversation::factory()->inSession($stillUsed)->create();

    $chatters = Session::factory()->forChatter()->create(['agent_id' => $agent->id]);
    pecaTrashed(['chat_session_id' => $chatters->id]);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 1, graceDays: 30);

    expect($result->purged)->toBe(4)
        ->and($result->orphanSessions)->toBe(1)
        ->and(Session::query()->find($emptied->id))->toBeNull()
        ->and(Session::query()->find($stillUsed->id))->not->toBeNull()
        ->and(Session::query()->find($chatters->id))->not->toBeNull();
});

it('keeps a never-used anonymous session until it is old', function (): void {
    $fresh = Session::factory()->create();

    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect(Session::query()->find($fresh->id))->not->toBeNull();
});

it('removes anonymous sessions left empty for a long time', function (): void {
    $empty = Session::factory()->anonymous()->create();
    DB::table('chat_sessions')->where('id', $empty->id)->update(['created_at' => now()->subDays(PruneExpiredConversationsAction::ORPHAN_SESSION_DAYS + 1)]);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect($result->orphanSessions)->toBe(1)
        ->and(Session::query()->find($empty->id))->toBeNull();
});

it('keeps an empty session that belongs to a chatter', function (): void {
    $chatterSession = Session::factory()->forChatter()->create();
    DB::table('chat_sessions')->where('id', $chatterSession->id)->update(['created_at' => now()->subDays(PruneExpiredConversationsAction::ORPHAN_SESSION_DAYS + 1)]);

    app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect(Session::query()->find($chatterSession->id))->not->toBeNull();
});
