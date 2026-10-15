<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Whatsapp\Actions\ReceiveWhatsappMessagesAction;
use Domain\Channel\Whatsapp\Data\WhatsappInboundMessageData;
use Domain\Channel\Whatsapp\Jobs\HandleWhatsappMessageJob;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Illuminate\Support\Facades\Bus;

/**
 * WhatsappWebhookTest covers routing, parsing and a repeated delivery through
 * the webhook. This covers what one number cannot show: the memory of queued
 * messages is kept per number, so two numbers that report the same message
 * id are each answered, and each only once.
 */
it('remembers queued messages per WhatsApp number', function (): void {
    Bus::fake();
    $number = fn (string $phoneNumberId): AgentWhatsappNumber => AgentWhatsappNumber::create([
        'agent_id' => Agent::factory()->create()->id,
        'access_token' => 'EAAG-wa-token',
        'phone_number_id' => $phoneNumberId,
        'waba_id' => '999',
        'enabled' => true,
    ]);
    $first = $number('111');
    $second = $number('222');
    $messages = [
        new WhatsappInboundMessageData('111', '4915551234567', 'hello', 'wamid.1', 'Sam'),
        new WhatsappInboundMessageData('222', '4915557654321', 'hi', 'wamid.1', 'Lee'),
    ];
    $action = app(ReceiveWhatsappMessagesAction::class);

    $action->execute($messages);
    $action->execute($messages);

    Bus::assertDispatchedTimes(HandleWhatsappMessageJob::class, 2);
    Bus::assertDispatched(HandleWhatsappMessageJob::class, fn (HandleWhatsappMessageJob $job): bool => $job->number->is($first) && $job->waId === '4915551234567' && $job->name === 'Sam');
    Bus::assertDispatched(HandleWhatsappMessageJob::class, fn (HandleWhatsappMessageJob $job): bool => $job->number->is($second) && $job->waId === '4915557654321' && $job->name === 'Lee');
});
