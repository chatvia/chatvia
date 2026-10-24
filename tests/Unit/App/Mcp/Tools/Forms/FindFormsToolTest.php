<?php

declare(strict_types=1);

use App\Mcp\Servers\FormsServer;
use App\Mcp\Tools\Forms\FindFormsTool;
use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Form\Models\FormSubmission;
use Tests\Support\Mcp\FormAgent;
use Tests\Support\Mcp\InternalMcp;

/**
 * find-forms is a contract with the LLM: the forms it may show and the
 * guidance texts around them. These snapshots pin the exact JSON-RPC result
 * of each path.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 6, 15, 12, 30, 0));
});

it('finds matching forms', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = FormAgent::seed();
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(InternalMcp::resultJson(FormsServer::tool(FindFormsTool::class, ['intent' => 'I want to book a demo'])))->toMatchSnapshot();
});

it('finds forms without a conversation', function (): void {
    ['agent' => $agent] = FormAgent::seed();
    InternalMcp::actAsAgent($agent->external_id);

    expect(InternalMcp::resultJson(FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo please'])))->toMatchSnapshot();
});

it('flags forms the visitor already submitted', function (): void {
    ['agent' => $agent, 'conversation' => $conversation, 'form' => $form] = FormAgent::seed();
    FormSubmission::factory()->forDefinition($form)->submitted()->create(['chat_conversation_id' => $conversation->id]);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(InternalMcp::resultJson(FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo'])))->toMatchSnapshot();
});

it('finds nothing for an agent without forms', function (): void {
    $agent = Agent::factory()->create();
    InternalMcp::actAsAgent($agent->external_id);

    expect(InternalMcp::resultJson(FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo'])))->toMatchSnapshot();
});

it('finds nothing on a messaging channel', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = FormAgent::seed(ConversationSource::Slack);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id);

    expect(InternalMcp::resultJson(FormsServer::tool(FindFormsTool::class, ['intent' => 'book a demo'])))->toMatchSnapshot();
});

it('explains each missing find-forms identity or input', function (?string $agent, array $arguments): void {
    ['agent' => $real] = FormAgent::seed();
    InternalMcp::actAsAgent($agent === '@agent' ? $real->external_id : $agent);

    expect(InternalMcp::resultJson(FormsServer::tool(FindFormsTool::class, $arguments)))->toMatchSnapshot();
})->with([
    'no agent header' => [null, ['intent' => 'book a demo']],
    'unknown agent' => ['ghost-agent', ['intent' => 'book a demo']],
    'no intent' => ['@agent', []],
]);
