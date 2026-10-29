<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Actions\RecordCrmDeliveryFailureAction;

/**
 * Counting failed CRM webhook deliveries: each failure increments the agent's
 * counter, and the webhook switches itself off once the configured threshold
 * of consecutive failures is reached.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Bot');

    $this->agent->setSetting('crm', [
        'webhook_enabled' => true,
        'webhook_url' => 'https://crm.example.com/hooks/leads',
        'webhook_secret' => 'shh',
    ], 'leads');
});

it('switches the webhook off after the configured number of consecutive failures', function (): void {
    config()->set('chatvia.crm.max_failures', 2);

    app(RecordCrmDeliveryFailureAction::class)->execute($this->agent);
    app(RecordCrmDeliveryFailureAction::class)->execute($this->agent->fresh());

    $crm = $this->agent->fresh()->settings('leads')->get('crm');

    expect($crm['webhook_failures'])->toBe(2)
        ->and($crm['webhook_enabled'])->toBeFalse();
});
