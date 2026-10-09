<?php

namespace Domain\Channel\Whatsapp\Actions;

use Domain\Channel\Services\InboundDeliveryLog;
use Domain\Channel\Whatsapp\Data\WhatsappInboundMessageData;
use Domain\Channel\Whatsapp\Jobs\HandleWhatsappMessageJob;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;

/**
 * Queues the user messages of one verified WhatsApp webhook delivery. One
 * app-level Callback URL receives every connected number, so each message is
 * routed to its agent by the phone number id that received it; messages for
 * an unknown or disabled number are ignored. Each message is queued at most
 * once — Meta re-sends unacknowledged WhatsApp webhooks for up to 7 days,
 * hence the matching 7-day memory.
 */
class ReceiveWhatsappMessagesAction
{
    public function __construct(
        private readonly InboundDeliveryLog $deliveries,
    ) {}

    /**
     * @param  list<WhatsappInboundMessageData>  $messages
     */
    public function execute(array $messages): void
    {
        /** @var array<string, AgentWhatsappNumber|null> $numbers one lookup per number in the delivery */
        $numbers = [];

        foreach ($messages as $message) {
            if (! array_key_exists($message->phoneNumberId, $numbers)) {
                $numbers[$message->phoneNumberId] = $this->number($message->phoneNumberId);
            }

            $number = $numbers[$message->phoneNumberId];

            if (! $number instanceof AgentWhatsappNumber
                || ! $this->deliveries->firstDelivery("wa:mid:{$number->id}:{$message->messageId}", now()->addDays(7))) {
                continue;
            }

            HandleWhatsappMessageJob::dispatch($number, $message->waId, $message->text, $message->messageId, $message->senderName);
        }
    }

    private function number(string $phoneNumberId): ?AgentWhatsappNumber
    {
        if ($phoneNumberId === '') {
            return null;
        }

        return AgentWhatsappNumber::query()
            ->with('agent')
            ->where('phone_number_id', $phoneNumberId)
            ->where('enabled', true)
            ->first();
    }
}
