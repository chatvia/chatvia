<?php

declare(strict_types=1);

namespace Domain\Channel\Whatsapp\Jobs;

use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Services\WhatsappAdapter;
use Domain\Channel\Whatsapp\Services\WhatsappChatResolver;
use Domain\Channel\Whatsapp\Services\WhatsappClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Carries one inbound WhatsApp message from the webhook (which must answer
 * quickly or Meta retries and eventually disables the subscription) to
 * {@see HandleChannelMessageAction} (as a {@see WhatsappAdapter}) on the queue. `$messageId` is the
 * inbound wamid — the handle for the read-receipt + typing indicator.
 */
// The chat queue: a reply never waits behind e-mails or embeddings.
#[Queue('messages')]
// The turn bills the owner on execution — never retry it.
#[Tries(1)]
// A reply may call the model and the tools several times.
#[Timeout(300)]
// A number disconnected before the job runs has no one to reply to.
#[DeleteWhenMissingModels]
class HandleWhatsappMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AgentWhatsappNumber $number,
        public readonly string $waId,
        public readonly string $text,
        public readonly string $messageId,
        public readonly ?string $name = null,
    ) {}

    public function handle(HandleChannelMessageAction $action, WhatsappClient $client, WhatsappChatResolver $resolver): void
    {
        $action->execute(
            new WhatsappAdapter($client, $resolver, $this->number, $this->waId, $this->messageId, $this->name),
            $this->text,
        );
    }
}
