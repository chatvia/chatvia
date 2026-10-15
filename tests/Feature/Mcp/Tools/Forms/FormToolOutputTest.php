<?php

use App\Mcp\Servers\FormsServer;
use App\Mcp\Tools\Forms\FindFormsTool;
use App\Mcp\Tools\Forms\RenderFormTool;
use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Laravel\Mcp\Server\Testing\TestResponse;
use Tests\Support\Mcp\InternalMcp;

/**
 * find-forms and render-form are a contract with the LLM (their guidance
 * texts) and with the chat UI's inline-form renderer (the `form` artifact).
 * These snapshots pin the exact JSON-RPC result of each path, with the
 * random form_instance_id masked.
 */
pest()->beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 6, 15, 12, 30, 0));
});

function formToolResult(TestResponse $response): string
{
    $body = (fn (): array => $this->response->toArray())->call($response);
    $json = json_encode($body['result'] ?? $body['error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    return (string) preg_replace('/"form_instance_id": "[^"]+"/', '"form_instance_id": "<uuid>"', $json);
}

/** @return array{agent: Agent, conversation: Conversation, form: FormDefinition} */
function seedFormAgent(ConversationSource $source = ConversationSource::Widget): array
{
    $agent = Agent::factory()->create();
    $form = FormDefinition::factory()->forAgent($agent)->create([
        'name' => 'Book a demo',
        'slug' => 'book-a-demo',
        'subtitle' => 'Pick a time that suits you.',
        'agent_instructions' => 'Show this when the visitor wants to book a demo.',
        'is_active' => true,
    ]);
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'chat_session_id' => $session->id, 'source' => $source]);

    return ['agent' => $agent, 'conversation' => $conversation, 'form' => $form];
}

it('renders a form artifact', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = seedFormAgent();
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(formToolResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])))->toMatchSnapshot();
});

it('renders the default success message when the form has none', function (): void {
    ['agent' => $agent, 'conversation' => $conversation, 'form' => $form] = seedFormAgent();
    $form->update(['success_message' => null, 'subtitle' => null]);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(formToolResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'Book a demo'])))->toMatchSnapshot();
});

it('acknowledges a form already submitted in the session', function (): void {
    ['agent' => $agent, 'conversation' => $conversation, 'form' => $form] = seedFormAgent();
    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $conversation->id]);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(formToolResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])))->toMatchSnapshot();
});

it('names the active slugs when the requested form does not exist', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = seedFormAgent();
    FormDefinition::factory()->forAgent($agent)->create(['name' => 'Get a quote', 'slug' => 'get-a-quote', 'is_active' => true]);
    FormDefinition::factory()->forAgent($agent)->create(['name' => 'Old form', 'slug' => 'old-form', 'is_active' => false]);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(formToolResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'newsletter'])))->toMatchSnapshot();
});

it('steers back to conversation when the agent has no active form', function (): void {
    ['agent' => $agent, 'conversation' => $conversation, 'form' => $form] = seedFormAgent();
    $form->update(['is_active' => false]);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(formToolResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])))->toMatchSnapshot()
        ->and(FormSubmission::query()->where('form_definition_id', $form->id)->exists())->toBeFalse();
});

it('refuses to render on a messaging channel', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = seedFormAgent(ConversationSource::Telegram);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(formToolResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])))->toMatchSnapshot();
});

it('explains each missing render-form identity', function (?string $agent, ?string $conversation): void {
    ['agent' => $real, 'conversation' => $realConversation] = seedFormAgent();
    $other = Conversation::factory()->create(['source' => ConversationSource::Widget]);

    $resolve = fn (?string $value): ?string => match ($value) {
        '@agent' => $real->external_id,
        '@conversation' => $realConversation->external_id,
        '@other-conversation' => $other->external_id,
        default => $value,
    };

    InternalMcp::actAsAgent($resolve($agent), $resolve($conversation));

    expect(formToolResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])))->toMatchSnapshot();
})->with([
    'no agent header' => [null, '@conversation'],
    'no conversation header' => ['@agent', null],
    'unknown agent' => ['ghost-agent', '@conversation'],
    'conversation of another agent' => ['@agent', '@other-conversation'],
]);

it('finds matching forms', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = seedFormAgent();
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(formToolResult(FormsServer::tool(FindFormsTool::class, ['intent' => 'I want to book a demo'])))->toMatchSnapshot();
});

it('finds forms without a conversation', function (): void {
    ['agent' => $agent] = seedFormAgent();
    InternalMcp::actAsAgent($agent->external_id);

    expect(formToolResult(FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo please'])))->toMatchSnapshot();
});

it('flags forms the visitor already submitted', function (): void {
    ['agent' => $agent, 'conversation' => $conversation, 'form' => $form] = seedFormAgent();
    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $conversation->id]);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(formToolResult(FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo'])))->toMatchSnapshot();
});

it('finds nothing for an agent without forms', function (): void {
    $agent = Agent::factory()->create();
    InternalMcp::actAsAgent($agent->external_id);

    expect(formToolResult(FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo'])))->toMatchSnapshot();
});

it('finds nothing on a messaging channel', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = seedFormAgent(ConversationSource::Slack);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(formToolResult(FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo'])))->toMatchSnapshot();
});

it('explains each missing find-forms identity or input', function (?string $agent, array $arguments): void {
    seedFormAgent();
    InternalMcp::actAsAgent($agent);

    expect(formToolResult(FormsServer::tool(FindFormsTool::class, $arguments)))->toMatchSnapshot();
})->with([
    'no agent header' => [null, ['intent' => 'book a demo']],
    'unknown agent' => ['ghost-agent', ['intent' => 'book a demo']],
]);

it('validates the find-forms intent', function (): void {
    ['agent' => $agent] = seedFormAgent();
    InternalMcp::actAsAgent($agent->external_id);

    expect(formToolResult(FormsServer::tool(FindFormsTool::class, [])))->toMatchSnapshot();
});
