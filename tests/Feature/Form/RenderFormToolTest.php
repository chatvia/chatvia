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

it('resolves the form when the model passes its display name instead of the slug', function (string $key): void {
    $agent = Agent::factory()->create();
    FormDefinition::factory()->forAgent($agent)->create(['name' => 'Book a demo', 'slug' => 'book-a-demo', 'is_active' => true]);
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'source' => ConversationSource::HostedChat]);

    renderAs($agent, $conversation);

    FormsServer::tool(RenderFormTool::class, ['slug' => $key])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('form.definition_slug', 'book-a-demo')
            ->etc());

    expect(FormSubmission::where('chat_conversation_id', $conversation->id)->where('status', FormSubmission::STATUS_PENDING)->exists())->toBeTrue();
})->with([
    'display name' => 'Book a demo',
    'upper-cased name' => 'BOOK A DEMO',
    'snake_case slug' => 'book_a_demo',
    'exact slug' => 'book-a-demo',
]);

it('refuses an unknown slug instead of fabricating a default form', function (): void {
    $agent = Agent::factory()->create();
    // A real, active form exists — but the model asks for a slug that isn't it.
    FormDefinition::factory()->forAgent($agent)->create(['slug' => 'book-a-demo', 'is_active' => true]);
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'source' => ConversationSource::HostedChat]);

    renderAs($agent, $conversation);

    FormsServer::tool(RenderFormTool::class, ['slug' => 'made-up-slug'])
        ->assertHasErrors();

    // Nothing was rendered: no form minted from a guessed slug or a default fallback.
    expect(FormSubmission::query()->exists())->toBeFalse();
});

it('refuses to render anything when no slug is given', function (): void {
    $agent = Agent::factory()->create();
    FormDefinition::factory()->forAgent($agent)->create(['slug' => 'book-a-demo', 'is_active' => true]);
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'source' => ConversationSource::HostedChat]);

    renderAs($agent, $conversation);

    FormsServer::tool(RenderFormTool::class, [])
        ->assertHasErrors();

    expect(FormSubmission::query()->exists())->toBeFalse();
});

it('does not render an inactive form even by its exact slug', function (): void {
    $agent = Agent::factory()->create();
    FormDefinition::factory()->forAgent($agent)->create(['slug' => 'book-a-demo', 'is_active' => false]);
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'source' => ConversationSource::HostedChat]);

    renderAs($agent, $conversation);

    FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])
        ->assertHasErrors();

    expect(FormSubmission::query()->exists())->toBeFalse();
});

it('does not record the server-to-server IP or user agent on a rendered form', function (): void {
    $agent = Agent::factory()->create();
    FormDefinition::factory()->forAgent($agent)->create(['slug' => 'book-a-demo', 'is_active' => true]);
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'source' => ConversationSource::HostedChat]);

    // The MCP call comes from Chatvia's own server, not the visitor.
    app(HttpRequest::class)->headers->set('User-Agent', 'GuzzleHttp/7');
    renderAs($agent, $conversation);

    FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])->assertOk();

    $pending = FormSubmission::where('chat_conversation_id', $conversation->id)->sole();
    expect($pending->ip_address)->toBeNull()
        ->and($pending->user_agent)->toBeNull();
});
