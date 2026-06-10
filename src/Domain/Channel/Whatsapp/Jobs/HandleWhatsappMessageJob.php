<?php

namespace Domain\Channel\Whatsapp\Jobs;

use Domain\Channel\Whatsapp\Actions\HandleWhatsappMessageAction;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Carries one inbound WhatsApp message from the webhook (which must answer
 * quickly or Meta retries and eventually disables the subscription) to
 * {@see HandleWhatsappMessageAction} on the queue. `$messageId` is the
 * inbound wamid — the handle for the read-receipt + typing indicator.
 */
class HandleWhatsappMessageJob implements ShouldQueue
{
    use Queueable;

    /** The turn bills the owner on execution — never retry it. */
    public int $tries = 1;

    public function __construct(
        public readonly AgentWhatsappNumber $number,
        public readonly string $waId,
        public readonly string $text,
        public readonly string $messageId,
        public readonly ?string $name = null,
    ) {}

    public function handle(HandleWhatsappMessageAction $action): void
    {
        $action->execute($this->number, $this->waId, $this->text, $this->messageId, $this->name);
    }
}
