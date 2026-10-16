<?php

namespace Domain\Channel\Messenger\Actions;

use Domain\Channel\Messenger\Data\MessengerInboundMessageData;
use Domain\Channel\Messenger\Jobs\HandleMessengerMessageJob;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Services\InboundDeliveryLog;

/**
 * Queues the user messages of one verified Messenger webhook delivery. One
 * app-level Callback URL receives every connected Page, so each message is
 * routed to its agent by Page id; messages for an unknown or disabled Page,
 * and the Page's own messages, are ignored. Each message is queued at most
 * once — Meta re-delivers with decreasing frequency for up to 36 hours, so the
 * 2-day memory outlives that window.
 */
class ReceiveMessengerMessagesAction
{
    public function __construct(
        private readonly InboundDeliveryLog $deliveries,
    ) {}

    /**
     * @param  list<MessengerInboundMessageData>  $messages
     */
    public function execute(array $messages): void
    {
        /** @var array<string, AgentMessengerPage|null> $pages one lookup per Page in the delivery */
        $pages = [];

        foreach ($messages as $message) {
            if (! array_key_exists($message->pageId, $pages)) {
                $pages[$message->pageId] = $this->page($message->pageId);
            }

            $page = $pages[$message->pageId];

            if (! $page instanceof AgentMessengerPage || $message->senderId === $page->page_id) {
                continue;
            }

            if ($message->messageId !== null
                && ! $this->deliveries->firstDelivery("messenger:mid:{$page->id}:{$message->messageId}", now()->addDays(2))) {
                continue;
            }

            HandleMessengerMessageJob::dispatch($page, $message->senderId, $message->text);
        }
    }

    private function page(string $pageId): ?AgentMessengerPage
    {
        if ($pageId === '') {
            return null;
        }

        return AgentMessengerPage::query()
            ->with('agent')
            ->where('page_id', $pageId)
            ->where('enabled', true)
            ->first();
    }
}
