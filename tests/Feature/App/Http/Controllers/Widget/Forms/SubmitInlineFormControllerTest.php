<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Widget\WidgetHeaders;
use Illuminate\Testing\TestResponse;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/forms/{formInstance}/submit takes the answers of an inline form
 * the agent rendered in the widget. The form must be the agent's and its
 * conversation reachable by the caller (on a protected agent: the signed-in
 * chatter's); a form that already took answers answers 409.
 * SubmitFormActionTest owns what is stored.
 */
beforeEach(function (): void {
    withLicense();
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);

    $this->headers = EmbeddedWidget::chatHeaders($this->agent);
    $this->form = wfsForm($this->agent, $this->headers[WidgetHeaders::CONVERSATION_ID]);
});

/** A form the agent rendered in this conversation. */
function wfsForm(Agent $agent, string $conversationExternalId): FormSubmission
{
    return FormSubmission::factory()
        ->forDefinition(FormDefinition::factory()->forAgent($agent)->create())
        ->create(['chat_conversation_id' => Conversation::query()->where('external_id', $conversationExternalId)->value('id')]);
}

/**
 * @param  array<string, string>  $headers
 */
function wfsSubmit(FormSubmission $form, array $headers): TestResponse
{
    return test()->withHeaders($headers)->postJson("/widget/forms/{$form->external_id}/submit", [
        'fields' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.test'],
    ]);
}

/**
 * The widget headers of a signed-in chatter, in a session of theirs.
 *
 * @return array<string, string>
 */
function wfsChatterHeaders(Agent $agent, Chatter $chatter): array
{
    $boot = EmbeddedWidget::bootSignedIn($agent, $chatter);

    return EmbeddedWidget::signedInHeaders($agent, $chatter, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]);
}

it('takes the visitor’s answers to the form the agent rendered once, and answers 409 after', function (): void {
    wfsSubmit($this->form, $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'submitted');

    wfsSubmit($this->form, $this->headers)
        ->assertConflict()
        ->assertJsonPath('status', 'already_submitted');

    expect($this->form->fresh()->status)->toBe(FormSubmissionStatus::Submitted);
});

it('takes the answers only from the caller whose conversation it is, a protected agent’s chatter too', function (): void {
    wfsSubmit($this->form, EmbeddedWidget::chatHeaders($this->agent))->assertForbidden();
    expect($this->form->fresh()->status)->toBe(FormSubmissionStatus::Pending);

    $agent = Agent::factory()->create(['is_public' => false]);
    EmbeddedWidget::allowSite($agent);
    $jane = Chatter::factory()->grantedTo($agent)->create();
    $omar = Chatter::factory()->grantedTo($agent)->create();
    $janeHeaders = wfsChatterHeaders($agent, $jane);
    $form = wfsForm($agent, $janeHeaders[WidgetHeaders::CONVERSATION_ID]);

    wfsSubmit($form, wfsChatterHeaders($agent, $omar))->assertForbidden();
    expect($form->fresh()->status)->toBe(FormSubmissionStatus::Pending);

    wfsSubmit($form, $janeHeaders)->assertOk();
    expect($form->fresh()->status)->toBe(FormSubmissionStatus::Submitted);
});
