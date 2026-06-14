<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;
use Stripe\Exception\ApiErrorException;
use Throwable;

/**
 * Tear down a whole workspace: the owner, their employees + chatters, their
 * teams, and (by FK cascade) every agent, conversation, knowledge source,
 * credit, billing row, etc. that hangs off them.
 *
 * What the FK cascade can't do, handled explicitly here:
 *  - Attachment files on S3 — the cascade drops the rows but never fires the
 *    Eloquent `deleting` event that wipes the object ({@see self::deleteAttachmentFiles()}).
 *  - The workspace's Redis chat memory — cache, not DB ({@see self::flushChatMemoryCache()}).
 *  - Captured leads — `leads.agent_id` / `chat_conversation_id` only SET NULL on
 *    the cascade, so the rows (which hold visitor PII) would survive; deleted by agent.
 *  - Sanctum tokens (`personal_access_tokens`, polymorphic — no FK) and HTTP
 *    `sessions` rows (no FK) for the owner and every sub-user.
 *
 * Only a workspace owner reaches here. Employees and chatters can't own teams,
 * so the teardown never recurses through sub-user-owned teams.
 */
class DeleteUserAccountAction
{
    public function execute(User $user): void
    {
        $this->cancelSubscriptions($user);

        // Wipe attachment files through Eloquent BEFORE the raw chat_messages
        // delete and the final user cascade. Both remove chat_attachments rows
        // by FK cascade (chat_message_id and chat_conversation_id are
        // cascadeOnDelete) WITHOUT firing the Attachment `deleting` observer —
        // so the S3 objects would leak. Deleting the models here fires the
        // observer ({@see Attachment::booted}) and wipes the files first.
        $this->deleteAttachmentFiles($user);

        // Pre-delete the unbounded children (chunks carry 2 vchordrq + 2 bm25 + GIN
        // indexes each) in batches outside the final transaction; the FK cascade
        // then only handles the bounded remainder.
        $this->batchDelete(
            'DELETE FROM chunks WHERE id IN (SELECT id FROM chunks WHERE user_id = ? LIMIT 10000)',
            [$user->id],
        );

        $this->batchDelete(
            'DELETE FROM chat_messages WHERE id IN (
                SELECT m.id FROM chat_messages m
                JOIN chat_conversations c ON c.id = m.chat_conversation_id
                JOIN agents a ON a.id = c.agent_id
                WHERE a.user_id = ?
                LIMIT 10000
            )',
            [$user->id],
        );

        // Leads hold the visitor's PII and only SET NULL on the agents →
        // conversations cascade, so delete the workspace's leads (keyed by its
        // agents) before the owner — and their agents — are torn down.
        DB::table('leads')
            ->whereIn('agent_id', fn ($query) => $query->select('id')->from('agents')->where('user_id', $user->id))
            ->delete();

        DB::transaction(function () use ($user): void {
            $ownedTeamIds = $user->ownedTeams()->pluck('id')->all();

            // Employees + chatters belonging to this workspace, plus the owner.
            $subUserIds = $user->users()->pluck('id')->all();
            $allUserIds = [...$subUserIds, $user->id];

            // Clear team membership first: team_user.team_id, .user_id and
            // .role_id are all RESTRICT, so the pivot blocks both deleting the
            // workspace's users and the owned-team cascade (team → roles would
            // trip team_user.role_id) until these rows are gone.
            DB::table('team_user')
                ->whereIn('team_id', $ownedTeamIds)
                ->orWhereIn('user_id', $allUserIds)
                ->delete();

            // Nothing cascades these, so the deleted users would orphan them:
            // Sanctum tokens are polymorphic (no FK) and the sessions table
            // carries no FK to users either.
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $user->getMorphClass())
                ->whereIn('tokenable_id', $allUserIds)
                ->delete();
            DB::table('sessions')->whereIn('user_id', $allUserIds)->delete();

            // Workspace sub-users — their owned data cascades on delete. Model
            // delete (not a bulk query) so UserObserver/HasRoles cleanup fires;
            // eager-load `billing` because UserObserver::deleting() reads it
            // (sub-users have none, but the access must not lazy-load).
            $user->users()->with('billing')->get()->each(fn ($subUser) => $subUser->delete());

            // Finally the owner; every remaining reference cascades or nulls out.
            $user->delete();
        });

        $this->flushChatMemoryCache($user);
    }

    /**
     * Wipe every conversation-bound attachment's S3 object by deleting the
     * models through Eloquent (firing {@see Attachment::booted}'s `deleting`
     * hook). Runs before the chat_messages batch-delete and the user cascade,
     * both of which would otherwise drop the rows via FK cascade without ever
     * touching S3. chunkById is delete-safe (it pages by `id > lastId`, so
     * removing the current page never skips rows). Orphan composer uploads
     * (no conversation, keyed only by agent path) aren't chat memory and are
     * left to the daily orphan-object prune.
     */
    private function deleteAttachmentFiles(User $user): void
    {
        Attachment::query()
            ->whereExists(function ($query) use ($user): void {
                $query->selectRaw('1')
                    ->from('chat_conversations as c')
                    ->join('agents as a', 'a.id', '=', 'c.agent_id')
                    ->whereColumn('c.id', 'chat_attachments.chat_conversation_id')
                    ->where('a.user_id', $user->id);
            })
            ->chunkById(500, static function ($attachments): void {
                $attachments->each(static fn (Attachment $attachment) => $attachment->delete());
            });
    }

    /**
     * Drop the workspace's Redis chat memory in one shot. Every chat-cache
     * entry carries the workspace tag ({@see ChatCache::workspaceTag()}), so a
     * single tag flush clears conversation lists, message/turn caches, source
     * caches and the `forever` ownership markers that would otherwise outlive
     * the deleted DB rows (they never expire). Best-effort: a teardown must not
     * fail because the cache store is briefly unreachable — log and carry on,
     * mirroring how the S3 and Stripe steps tolerate downstream failures.
     */
    private function flushChatMemoryCache(User $user): void
    {
        try {
            Cache::driver('chat')->tags(ChatCache::workspaceTag($user->external_id))->flush();
        } catch (Throwable $e) {
            Log::warning('Account deletion: failed to flush workspace chat memory cache.', [
                'workspace_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Loop a bounded DELETE until it stops matching rows. */
    private function batchDelete(string $sql, array $bindings): void
    {
        do {
            $deleted = DB::affectingStatement($sql, $bindings);
        } while ($deleted > 0);
    }

    /**
     * Immediately cancel every Stripe subscription on the workspace's billing (a
     * workspace can hold more than one — e.g. add-ons as separate subscriptions)
     * before the teardown cascade-deletes the `user_billing` row. This is the
     * single place account deletion talks to Stripe, and it runs *outside* the
     * DB transaction so a slow or failing Stripe call never holds it open.
     *
     * We use cancelNow() — not a grace-period cancel() — so we don't orphan a
     * still-billing subscription. Already-canceled subscriptions are skipped: a
     * subscription Stripe has already closed must not be cancelled again, which
     * Stripe rejects ("A canceled subscription can only update its
     * cancellation_details and metadata") — even when it still looks "subscribed"
     * locally because its trial window hasn't lapsed.
     *
     * If Stripe rejects a call — most often because the subscription no longer
     * exists there (stale/test data: "No such subscription") — it can't bill
     * anyone, so we log it, mark it canceled locally, and carry on rather than
     * failing the whole account deletion.
     */
    private function cancelSubscriptions(User $user): void
    {
        $billing = $user->billing;

        if ($billing === null) {
            return;
        }

        /** @var Subscription $subscription */
        foreach ($billing->subscriptions()->get() as $subscription) {
            if ($subscription->canceled()) {
                continue;
            }

            try {
                $subscription->cancelNow();
            } catch (ApiErrorException $e) {
                // Stripe rejected the cancel — most often the subscription no
                // longer exists there (stale/test data: "No such subscription")
                // or is already canceled. It can't bill anyone, so mark it
                // canceled locally and carry on rather than failing the whole
                // account deletion.
                Log::warning('Account deletion: Stripe subscription cancel failed; marking canceled locally.', [
                    'subscription_id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'error' => $e->getMessage(),
                ]);

                $subscription->markAsCanceled();
            }
        }
    }
}
