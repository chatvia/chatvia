<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * POST chat/{agentSlug}/forms/{formInstance}/submit stores the answers of an
 * inline form the agent rendered in the hosted chat. The form instance must be
 * one the agent rendered, and it takes answers once.
 */
beforeEach(function (): void {
    // Capabilities and forms need a license key; EnsureCapabilityLicenseTest covers the gate.
    withLicense();
    Event::fake([FormSubmitted::class]);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->base = "/chat/{$this->agent->slug}";
    $this->definition = FormDefinition::factory()->forAgent($this->agent)->create();

    // The visitor's conversation, where the agent rendered the form.
    $conversationId = HostedChatVisitor::startConversation($this->agent, 'I want a demo');
    $this->form = FormSubmission::factory()->forDefinition($this->definition)->create([
        'chat_conversation_id' => Conversation::query()->where('external_id', $conversationId)->value('id'),
    ]);
});

function sifcSubmit(string $base, FormSubmission $form): TestResponse
{
    return test()->postJson("{$base}/forms/{$form->external_id}/submit", [
        'fields' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.test'],
    ]);
}

it('stores the visitor\'s answers to the form the agent rendered', function (): void {
    sifcSubmit($this->base, $this->form)
        ->assertOk()
        ->assertJsonPath('status', 'submitted')
        ->assertJsonPath('submitted_values.email', 'ada@example.test');

    $stored = $this->form->fresh();
    expect($stored->status)->toBe(FormSubmissionStatus::Submitted)
        ->and($stored->values)->toBe(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
    Event::assertDispatched(FormSubmitted::class);
});

it('answers 404 for a form another agent rendered', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);

    sifcSubmit("/chat/{$other->slug}", $this->form)->assertNotFound();

    expect($this->form->fresh()->status)->toBe(FormSubmissionStatus::Pending);
    Event::assertNotDispatched(FormSubmitted::class);
});

it('takes the answers only once', function (): void {
    sifcSubmit($this->base, $this->form)->assertOk();

    sifcSubmit($this->base, $this->form)
        ->assertConflict()
        ->assertJsonPath('status', 'already_submitted');

    Event::assertDispatchedTimes(FormSubmitted::class, 1);
});

it('answers 404 for a form rendered in another visitor\'s conversation', function (): void {
    // A new browser: another visitor, in a chat session of their own.
    $this->flushSession();
    HostedChatVisitor::startConversation($this->agent, 'Hello');

    sifcSubmit($this->base, $this->form)->assertNotFound();

    expect($this->form->fresh()->status)->toBe(FormSubmissionStatus::Pending);
    Event::assertNotDispatched(FormSubmitted::class);
});
