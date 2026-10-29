<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Retention\PruneExpiredConversationsAction;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The conversation sweep: conversations past their horizon go to the trash
 * with their live memory and files, the trash is purged after the grace
 * window (with any file a bulk soft delete left behind), horizons stamped
 * under a longer retention are pulled back to the current one, and
 * anonymous sessions left empty go too. Chatter sessions stay.
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

it('trashes expired conversations with their memory and files, and purges the trash after the grace window', function (): void {
    $expired = Conversation::factory()->expiresAt(now()->subDay())->create();
    $upload = Attachment::factory()->forConversation($expired)->stored()->create();
    $media = GeneratedMedia::factory()->forConversation($expired)->stored()->create();
    $live = Conversation::factory()->expiresAt(now()->addDay())->create();
    $longGone = Conversation::factory()->create();
    $leftUpload = Attachment::factory()->forConversation($longGone)->stored()->create();
    $leftMedia = GeneratedMedia::factory()->forConversation($longGone)->stored()->create();
    DB::table('chat_conversations')->where('id', $longGone->id)->update(['deleted_at' => now()->subDays(40)]);
    $cache = Cache::driver('chat');
    $cache->tags(ChatCache::conversationTag($expired->external_id))->put('messages', 'cached');
    $cache->tags(ChatCache::conversationTag($live->external_id))->put('messages', 'cached');

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 50, graceDays: 30);

    expect($result->expired)->toBe(1)
        ->and($result->purged)->toBe(1)
        ->and(Conversation::withTrashed()->find($expired->id)->trashed())->toBeTrue()
        ->and($live->fresh()->trashed())->toBeFalse()
        ->and(Conversation::withTrashed()->whereKey($longGone->id)->exists())->toBeFalse()
        ->and($cache->tags(ChatCache::conversationTag($expired->external_id))->get('messages'))->toBeNull()
        ->and($cache->tags(ChatCache::conversationTag($live->external_id))->get('messages'))->toBe('cached')
        ->and(Attachment::query()->whereKey([$upload->id, $leftUpload->id])->exists())->toBeFalse()
        ->and(GeneratedMedia::query()->whereKey([$media->id, $leftMedia->id])->exists())->toBeFalse();
    Storage::disk(Attachment::DISK)->assertMissing([$upload->filepath, $leftUpload->filepath]);
    Storage::disk(GeneratedMedia::DISK)->assertMissing([$media->filepath, $leftMedia->filepath]);
});

it('pulls horizons beyond the current retention back to it', function (): void {
    config(['chatvia.retention.conversations_days' => 30]);
    $activeAt = now()->subDays(10)->startOfSecond();
    $insideHorizon = now()->addDays(10)->startOfSecond();
    $farHorizon = now()->addDays(300)->startOfSecond();
    $active = Conversation::factory()->expiresAt($farHorizon)->create();
    DB::table('chat_conversations')->where('id', $active->id)->update(['updated_at' => $activeAt]);
    $idle = Conversation::factory()->expiresAt($farHorizon)->create();
    DB::table('chat_conversations')->where('id', $idle->id)->update(['updated_at' => now()->subDays(40)]);
    $inside = Conversation::factory()->expiresAt($insideHorizon)->create();
    $trashed = pecaTrashed(['expires_at' => $farHorizon], daysAgo: 0);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 1, graceDays: 30);

    expect($result->clamped)->toBe(2)
        ->and($active->fresh()->expires_at->equalTo($activeAt->copy()->addDays(30)))->toBeTrue()
        ->and($active->fresh()->trashed())->toBeFalse()
        ->and(Conversation::withTrashed()->find($idle->id)->trashed())->toBeTrue()
        ->and($inside->fresh()->expires_at->equalTo($insideHorizon))->toBeTrue()
        ->and(Conversation::withTrashed()->find($trashed->id)->expires_at->equalTo($farHorizon))->toBeTrue();
});

it('removes only anonymous sessions left empty by the purge or for a long time, never a chatter\'s', function (): void {
    $agent = Agent::factory()->create();
    $emptied = Session::factory()->create(['agent_id' => $agent->id]);
    pecaTrashed(['chat_session_id' => $emptied->id]);
    $stillUsed = Session::factory()->create(['agent_id' => $agent->id]);
    pecaTrashed(['chat_session_id' => $stillUsed->id]);
    Conversation::factory()->inSession($stillUsed)->create();
    $chatters = Session::factory()->forChatter()->create(['agent_id' => $agent->id]);
    pecaTrashed(['chat_session_id' => $chatters->id]);
    $neverUsed = Session::factory()->create();
    $longEmpty = Session::factory()->anonymous()->create();
    $oldChatter = Session::factory()->forChatter()->create();
    DB::table('chat_sessions')->whereIn('id', [$longEmpty->id, $oldChatter->id])
        ->update(['created_at' => now()->subDays(PruneExpiredConversationsAction::ORPHAN_SESSION_DAYS + 1)]);

    $result = app(PruneExpiredConversationsAction::class)->execute(chunk: 1, graceDays: 30);

    expect($result->orphanSessions)->toBe(2)
        ->and(Session::query()->whereKey([$emptied->id, $longEmpty->id])->exists())->toBeFalse()
        ->and(Session::query()->whereKey([$stillUsed->id, $chatters->id, $neverUsed->id, $oldChatter->id])->count())->toBe(4);
});
