<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Llm\Operations\Query\TitleGenerator;
use Domain\Widget\WidgetHeaders;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/conversations/title names the visitor's conversation at once,
 * after the first reply, instead of waiting for the queued naming job. A
 * title that is there is kept without another AI call. It names no
 * conversation of another agent or another session, and needs both ids. The
 * model is the title generator, mocked at that boundary.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
    $this->boot = EmbeddedWidget::boot($this->agent);
    app(ChatMemory::class)
        ->withContext($this->agent, $this->boot['session_id'], $this->boot['conversation_id'])
        ->appendMessage(new MessageData(id: 'q1', role: MessageRole::User, content: 'Where is my order?', type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));
});

function ctcName(Agent $agent, string $sessionId, string $conversationId): TestResponse
{
    return test()->withHeaders(EmbeddedWidget::headers($agent, [
        WidgetHeaders::SESSION_ID => $sessionId,
        WidgetHeaders::CONVERSATION_ID => $conversationId,
    ]))->postJson('/widget/conversations/title');
}

it('names the conversation at once when the widget asks, after the first reply', function (): void {
    $this->mock(TitleGenerator::class, function (MockInterface $mock): void {
        $mock->shouldReceive('generate')->once()->with('Where is my order?')->andReturn('Order status');
    });

    ctcName($this->agent, $this->boot['session_id'], $this->boot['conversation_id'])
        ->assertOk()
        ->assertExactJson(['title' => 'Order status']);
});

it('keeps a title that is there, without another AI call', function (): void {
    Conversation::query()->firstWhere('external_id', $this->boot['conversation_id'])->update(['title' => 'Billing question']);
    $this->mock(TitleGenerator::class)->shouldNotReceive('generate');

    ctcName($this->agent, $this->boot['session_id'], $this->boot['conversation_id'])
        ->assertOk()
        ->assertExactJson(['title' => 'Billing question']);
});

it('names no conversation of another agent or another session', function (Closure $conversationId): void {
    $this->mock(TitleGenerator::class)->shouldNotReceive('generate');

    ctcName($this->agent, $this->boot['session_id'], $conversationId->call($this))
        ->assertNotFound()
        ->assertJson(['error' => 'Invalid thread']);
})->with([
    'another agent’s conversation' => [function (): string {
        $other = Agent::factory()->create(['is_public' => true]);
        EmbeddedWidget::allowSite($other);

        return EmbeddedWidget::boot($other)['conversation_id'];
    }],
    'another session’s conversation' => [fn (): string => EmbeddedWidget::boot($this->agent)['conversation_id']],
]);

it('needs a session and a conversation to name one', function (): void {
    $this->withHeaders(EmbeddedWidget::headers($this->agent))
        ->postJson('/widget/conversations/title')
        ->assertBadRequest();
});
