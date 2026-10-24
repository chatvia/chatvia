<?php

declare(strict_types=1);

use App\Mcp\Servers\FormsServer;
use App\Mcp\Tools\Forms\RenderFormTool;
use Carbon\CarbonImmutable;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Tests\Support\Mcp\FormAgent;
use Tests\Support\Mcp\InternalMcp;

/**
 * render-form is a contract with the LLM (its guidance texts) and with the
 * chat UI's inline-form renderer (the `form` artifact). These snapshots pin
 * the exact JSON-RPC result of each path, with the random form_instance_id
 * masked; a refusal renders nothing.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 6, 15, 12, 30, 0));
});

function rftResult(TestResponse $response): string
{
    return (string) preg_replace('/"form_instance_id": "[^"]+"/', '"form_instance_id": "<uuid>"', InternalMcp::resultJson($response));
}

it('renders a form artifact', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = FormAgent::seed();
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(rftResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])))->toMatchSnapshot();
});

it('renders the default success message when the form has none', function (): void {
    ['agent' => $agent, 'conversation' => $conversation, 'form' => $form] = FormAgent::seed();
    $form->update(['success_message' => null, 'subtitle' => null]);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(rftResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'Book a demo'])))->toMatchSnapshot();
});

it('acknowledges a form already submitted in the session', function (): void {
    ['agent' => $agent, 'conversation' => $earlier, 'form' => $form] = FormAgent::seed();
    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $earlier->id]);
    // A later chat of the same visitor: the session, not the chat, has submitted.
    $later = Conversation::factory()->create(['agent_id' => $agent->id, 'chat_session_id' => $earlier->chat_session_id, 'source' => ConversationSource::Widget]);
    InternalMcp::actAsAgent($agent->external_id, $later->external_id);

    expect(rftResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])))->toMatchSnapshot()
        ->and(FormSubmission::query()->where('chat_conversation_id', $later->id)->exists())->toBeFalse();
});

it('still renders the form for a session that has not submitted it', function (): void {
    ['agent' => $agent, 'conversation' => $other, 'form' => $form] = FormAgent::seed(ConversationSource::HostedChat);
    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $other->id]);
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'chat_session_id' => $session->id, 'source' => ConversationSource::HostedChat]);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->has('form')->etc());

    expect(FormSubmission::query()->where('chat_conversation_id', $conversation->id)->where('status', FormSubmissionStatus::Pending)->exists())->toBeTrue();
});

it('names the active slugs when the requested form does not exist', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = FormAgent::seed();
    FormDefinition::factory()->forAgent($agent)->create(['name' => 'Get a quote', 'slug' => 'get-a-quote', 'is_active' => true]);
    FormDefinition::factory()->forAgent($agent)->create(['name' => 'Old form', 'slug' => 'old-form', 'is_active' => false]);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(rftResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'newsletter'])))->toMatchSnapshot()
        ->and(FormSubmission::query()->exists())->toBeFalse();
});

it('steers back to conversation when the agent has no active form', function (): void {
    ['agent' => $agent, 'conversation' => $conversation, 'form' => $form] = FormAgent::seed();
    $form->update(['is_active' => false]);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(rftResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])))->toMatchSnapshot()
        ->and(FormSubmission::query()->exists())->toBeFalse();
});

it('refuses to render on a messaging channel', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = FormAgent::seed(ConversationSource::Telegram);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(rftResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])))->toMatchSnapshot()
        ->and(FormSubmission::query()->exists())->toBeFalse();
});

it('explains each missing render-form identity', function (?string $agent, ?string $conversation): void {
    ['agent' => $real, 'conversation' => $realConversation] = FormAgent::seed();
    $other = Conversation::factory()->create(['source' => ConversationSource::Widget]);

    $resolve = fn (?string $value): ?string => match ($value) {
        '@agent' => $real->external_id,
        '@conversation' => $realConversation->external_id,
        '@other-conversation' => $other->external_id,
        default => $value,
    };

    InternalMcp::actAsAgent($resolve($agent), $resolve($conversation));

    expect(rftResult(FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])))->toMatchSnapshot()
        ->and(FormSubmission::query()->exists())->toBeFalse();
})->with([
    'no agent header' => [null, '@conversation'],
    'no conversation header' => ['@agent', null],
    'unknown agent' => ['ghost-agent', '@conversation'],
    'conversation of another agent' => ['@agent', '@other-conversation'],
]);

it('refuses to render anything when no slug is given', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = FormAgent::seed();
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    FormsServer::tool(RenderFormTool::class, [])->assertHasErrors(['The slug field is required.']);

    expect(FormSubmission::query()->exists())->toBeFalse();
});

it('does not record the server-to-server IP or user agent on a rendered form', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = FormAgent::seed();
    // The MCP call comes from Chatvia's own server, not the visitor.
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id, ['User-Agent' => 'GuzzleHttp/7']);

    FormsServer::tool(RenderFormTool::class, ['slug' => 'book-a-demo'])->assertOk();

    $pending = FormSubmission::query()->where('chat_conversation_id', $conversation->id)->sole();
    expect($pending->ip_address)->toBeNull()
        ->and($pending->user_agent)->toBeNull();
});
