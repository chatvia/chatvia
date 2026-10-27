<?php

namespace Domain\Channel\Services;

use Closure;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Shared find-or-recreate skeleton for the per-channel resolvers (Telegram,
 * WhatsApp, Messenger, Instagram, Slack, Teams). Each maps a channel-native
 * identity (chat id, PSID, channel id, …) onto a persistent Chatvia
 * {@see Session} + Conversation, so a user keeps one ongoing conversation (and
 * its history) across messages.
 *
 * Subclasses supply only the parts that vary: the lookup query, the stable
 * identity columns, a create-time context (title, session name, extra columns)
 * and the {@see ConversationSource}. The skeleton owns the retention-prune
 * recovery and the atomic three-write create sequence.
 *
 * @template TMapping of Model&\Domain\Channel\Contracts\ChannelConversationMapping
 */
abstract class ChannelConversationResolver
{
    public function __construct(
        protected readonly StartConversationAction $startConversation,
    ) {}

    /**
     * Find the mapping row for this channel identity (reusing its Session +
     * Conversation), or mint a fresh Session + Conversation + mapping row.
     *
     * The create context is a closure so any cost it carries — a best-effort
     * profile/channel lookup for the title (Messenger, Instagram, Slack) — runs
     * only on the create path, never on the hot reuse path.
     *
     * @param  Builder<TMapping>  $lookup  scoped query that returns the existing mapping row, if any
     * @param  array<string, mixed>  $identity  stable identity columns for the mapping row (e.g. the channel foreign key + native id)
     * @param  Closure(): array{title: string, name?: string|null, attributes?: array<string, mixed>}  $context  create-time bits: conversation title, optional session display name, optional extra mapping columns
     * @param  (Closure(TMapping): void)|null  $onReuse  optional hook to refresh volatile columns on a returning row (e.g. Teams' service_url)
     * @return TMapping
     */
    protected function resolveMapping(
        Builder $lookup,
        Agent $agent,
        array $identity,
        ConversationSource $source,
        Closure $context,
        ?Closure $onReuse = null,
    ): Model {
        $existing = $lookup->first();

        if ($existing !== null) {
            // conversation() excludes soft-deleted rows, so a retention-pruned
            // conversation reads as missing here.
            if ($existing->conversation()->exists()) {
                if ($onReuse instanceof Closure) {
                    $onReuse($existing);
                }

                return $existing;
            }

            // Conversation was retention-pruned — drop the stale mapping and
            // mint a fresh session + conversation below.
            $existing->delete();
        }

        $context = $context();
        $name = $context['name'] ?? null;

        // The three writes (session, conversation, mapping) must land together:
        // a failure on the final write would otherwise leak an orphan Session +
        // Conversation that no channel row points at.
        return DB::transaction(function () use ($agent, $name, $context, $source, $identity): Model {
            $session = Session::query()->create([
                'workspace_id' => $agent->workspace_id,
                'agent_id' => $agent->id,
                'user_id' => null,
                'name' => $name,
            ]);

            $conversationId = $this->startConversation->execute(
                new ChatScopeData($agent, $session->external_id),
                $source,
                $context['title'],
            )->external_id;

            $conversation = $session->conversations()->where('external_id', $conversationId)->firstOrFail();

            return $this->createMapping([
                ...$identity,
                ...($context['attributes'] ?? []),
                'workspace_id' => $agent->workspace_id,
                'chat_session_id' => $session->id,
                'chat_conversation_id' => $conversation->id,
                'last_message_at' => now(),
            ]);
        });
    }

    /**
     * Persist the mapping row from the merged attributes. Subclasses return
     * `Model::create($attributes)` for their concrete mapping model.
     *
     * @param  array<string, mixed>  $attributes
     * @return TMapping
     */
    abstract protected function createMapping(array $attributes): Model;
}
