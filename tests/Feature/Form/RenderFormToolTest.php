<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Integration\Mcp\Services\Servers\FormsServer;
use Domain\Integration\Mcp\Services\Tools\RenderFormTool;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * render-form refuses to re-render a form the visitor already submitted, scoped to
 * the SESSION (the visitor's identity across conversations) — so a booking made in
 * an earlier chat in the same session stops the form showing again.
 */
pest()->beforeEach(function (): void {
    if (! Schema::hasColumn('form_definitions', 'embedding')) {
        $this->markTestSkipped('schema out of date — migrate the testing DB.');
    }
});

function renderAs(Agent $agent, Conversation $conversation): void
{
    $http = app(HttpRequest::class);
    $http->headers->set('X-Chatvia-Agent-Id', $agent->external_id);
    $http->headers->set('X-Chatvia-Conversation-Id', $conversation->external_id);
}

it('refuses to re-render a form the visitor already submitted in the same session', function (): void {
    $agent = Agent::factory()->create();
    $form = FormDefinition::factory()->forAgent($agent)->create(['slug' => 'book-a-demo', 'is_active' => true]);
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $first = Conversation::factory()->create(['agent_id' => $agent->id, 'chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);
    $second = Conversation::factory()->create(['agent_id' => $agent->id, 'chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);

    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $first->id]);

    renderAs($agent, $second);

    FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])
        ->assertOk()
        ->assertSee('already submitted');

    // No fresh (pending) render was created for the second conversation.
    expect(FormSubmission::where('chat_conversation_id', $second->id)->exists())->toBeFalse();
});

it('still renders the form for a different session that has not submitted', function (): void {
    $agent = Agent::factory()->create();
    $form = FormDefinition::factory()->forAgent($agent)->create(['slug' => 'book-a-demo', 'is_active' => true]);

    // Session A submitted the form.
    $sessionA = Session::factory()->create(['agent_id' => $agent->id]);
    $convA = Conversation::factory()->create(['agent_id' => $agent->id, 'chat_session_id' => $sessionA->id, 'source' => ConversationSource::HostedChat]);
    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $convA->id]);

    // Session B (a different visitor) has not — the form renders for them.
    $sessionB = Session::factory()->create(['agent_id' => $agent->id]);
    $convB = Conversation::factory()->create(['agent_id' => $agent->id, 'chat_session_id' => $sessionB->id, 'source' => ConversationSource::HostedChat]);

    renderAs($agent, $convB);

    FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->has('form')->etc());

    expect(FormSubmission::where('chat_conversation_id', $convB->id)->where('status', FormSubmission::STATUS_PENDING)->exists())->toBeTrue();
});
