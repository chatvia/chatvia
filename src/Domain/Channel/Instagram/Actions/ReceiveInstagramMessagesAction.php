<?php

namespace Domain\Channel\Instagram\Actions;

use Domain\Channel\Instagram\Data\InstagramInboundMessageData;
use Domain\Channel\Instagram\Jobs\HandleInstagramMessageJob;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Services\InboundDeliveryLog;

/**
 * Queues the user messages of one verified Instagram Messaging webhook
 * delivery. One app-level Callback URL receives every connected account, so
 * each message is routed to its agent by IG account id; messages for an
 * unknown or disabled account, and the account's own messages, are ignored.
 * Each message is queued at most once — Meta re-delivers with decreasing
 * frequency for up to 36 hours, so the 2-day memory outlives that window.
 */
class ReceiveInstagramMessagesAction
{
    public function __construct(
        private readonly InboundDeliveryLog $deliveries,
    ) {}

    /**
     * @param  list<InstagramInboundMessageData>  $messages
     */
    public function execute(array $messages): void
    {
        /** @var array<string, AgentInstagramAccount|null> $accounts one lookup per account in the delivery */
        $accounts = [];

        foreach ($messages as $message) {
            if (! array_key_exists($message->accountId, $accounts)) {
                $accounts[$message->accountId] = $this->account($message->accountId);
            }

            $account = $accounts[$message->accountId];

            if (! $account instanceof AgentInstagramAccount || $message->senderId === $account->ig_id) {
                continue;
            }

            if ($message->messageId !== null
                && ! $this->deliveries->firstDelivery("instagram:mid:{$account->id}:{$message->messageId}", now()->addDays(2))) {
                continue;
            }

            HandleInstagramMessageJob::dispatch($account, $message->senderId, $message->text);
        }
    }

    private function account(string $igId): ?AgentInstagramAccount
    {
        if ($igId === '') {
            return null;
        }

        return AgentInstagramAccount::query()
            ->with('agent')
            ->where('ig_id', $igId)
            ->where('enabled', true)
            ->first();
    }
}
