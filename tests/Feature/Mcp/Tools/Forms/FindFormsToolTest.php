<?php

use App\Mcp\McpCallerContext;
use App\Mcp\McpCallerResolver;
use App\Mcp\Servers\FormsServer;
use App\Mcp\Tools\Forms\FindFormsTool;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * find-forms is the dynamic discovery half of the Forms MCP server: the model
 * searches the agent's forms by the visitor's intent (no catalog is injected into
 * the system prompt). Forms are embedded inline by the observer on create, so the
 * dense + sparse columns are populated without touching the network.
 */
pest()->beforeEach(function (): void {
    if (! Schema::hasColumn('form_definitions', 'embedding')) {
        $this->markTestSkipped('schema out of date — migrate the testing DB.');
    }
});

function asAgent(Agent $agent, ?Conversation $conversation = null): void
{
    $request = Request::create('/mcp/forms', 'POST');
    $request->headers->set('X-Chatvia-Agent-Id', $agent->external_id);
    if ($conversation) {
        $request->headers->set('X-Chatvia-Conversation-Id', $conversation->external_id);
    }

    app()->instance(McpCallerContext::class, app(McpCallerResolver::class)->resolve($request));
}

it('returns the agent\'s forms with their slug for a matching intent', function (): void {
    $agent = Agent::factory()->create();
    FormDefinition::factory()->forAgent($agent)->create([
        'name' => 'Book a demo', 'slug' => 'book-a-demo', 'is_active' => true,
        'agent_instructions' => 'Show this when the visitor wants to book a demo or talk to sales.',
    ]);

    asAgent($agent);

    FormsServer::tool(FindFormsTool::class, ['intent' => 'I want to book a demo'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('forms', 1)
            ->where('forms.0.slug', 'book-a-demo')
            ->where('forms.0.name', 'Book a demo')
            ->etc());
});

it('returns no forms for an agent that has none', function (): void {
    $agent = Agent::factory()->create();

    asAgent($agent);

    FormsServer::tool(FindFormsTool::class, ['intent' => 'anything'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->where('forms', []));
});

it('surfaces no forms on a messaging channel (web-only artifact)', function (): void {
    $agent = Agent::factory()->create();
    FormDefinition::factory()->forAgent($agent)->create([
        'name' => 'Book a demo', 'slug' => 'book-a-demo', 'is_active' => true,
    ]);
    $conversation = Conversation::factory()->create([
        'agent_id' => $agent->id,
        'source' => ConversationSource::Telegram,
    ]);

    asAgent($agent, $conversation);

    FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->where('forms', []));
});

it('flags a form the visitor already submitted in this session', function (): void {
    $agent = Agent::factory()->create();
    $form = FormDefinition::factory()->forAgent($agent)->create([
        'name' => 'Book a demo', 'slug' => 'book-a-demo', 'is_active' => true,
        'agent_instructions' => 'Show this when the visitor wants to book a demo.',
    ]);
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create([
        'agent_id' => $agent->id, 'chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat,
    ]);
    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $conversation->id]);

    asAgent($agent, $conversation);

    FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('forms.0.slug', 'book-a-demo')
            ->where('forms.0.already_submitted', true)
            ->etc());
});

it('errors without the agent identity header', function (): void {
    app()->instance(McpCallerContext::class, app(McpCallerResolver::class)->resolve(Request::create('/mcp/forms', 'POST')));

    FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo'])
        ->assertHasErrors();
});
