<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Voice\VoiceCallSession;
use Domain\Conversation\Services\Voice\VoiceToolRunner;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Contracts\ToolDispatcher;
use Domain\Integration\Tool\Data\ToolDispatchResultData;
use Domain\Integration\Tool\Data\ToolEntryData;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Domain\Llm\Contracts\RealtimeCalls;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Conversation\Voice\RealtimeEvents;
use Tests\Support\Conversation\Voice\RecordingVoiceConnection;
use Tests\Support\Conversation\Voice\ScriptedRealtimeCalls;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * An action that needs approval in a voice call: the call holds it and has
 * the model ask first, waits for the yes to be written down, runs it when the
 * model repeats it after the visitor's spoken yes (audited with their words),
 * drops it when the answer was not a yes or the visitor talked over the
 * question, and never offers it to an anonymous visitor. Which repeat counts
 * as an answer is VoiceToolApprovals' rule; what counts as a yes is
 * SpokenYes'.
 */
beforeEach(function (): void {
    Queue::fake();
    Event::fake([AssistantReplyCompleted::class, ToolCallCompleted::class]);
    app()->instance(RealtimeCalls::class, new ScriptedRealtimeCalls);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('enabled', false, 'guardrails');
    $this->chatter = Chatter::factory()->ofWorkspace($this->agent->workspace)->create();
    $this->call = VoiceCalls::started(Conversation::factory()->forAgent($this->agent)->hostedChat()->create(), chatter: $this->chatter);

    $this->tool = 'mcp__stripe__send_stripe_feedback';
    $this->mock(ToolRegistry::class)->shouldReceive('for')->andReturn([
        new ToolEntryData(ToolKind::Mcp, '1', $this->tool, 'Send feedback to Stripe.', ['type' => 'object', 'properties' => ['feedback' => ['type' => 'string']]]),
    ]);
    $this->mock(ToolApprovalPolicy::class)->shouldReceive('requiresApproval')->andReturn(true);
    $this->dispatcher = $this->mock(ToolDispatcher::class);

    $this->session = app(VoiceCallSession::class)->for($this->call, $this->agent);
    $this->session->opened($this->connection = new RecordingVoiceConnection);
});

it('holds the action until the visitor\'s yes is written down, then runs it and audits their words', function (): void {
    $this->dispatcher->shouldReceive('dispatch')->once()->andReturn(ToolDispatchResultData::text('Feedback sent.'));

    RealtimeEvents::visitorSays($this->session, 'item_1', 'Send Stripe a thank-you note.');
    $this->session->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));
    $this->session->received(RealtimeEvents::committed('item_2'));
    $this->session->received(RealtimeEvents::toolCall('resp_2', $this->tool, '{ "feedback": "Thank you!" }', 'fc_2', 'item_2'));
    $beforeYes = $this->connection->ofType('conversation.item.create');
    Event::assertNotDispatched(ToolCallCompleted::class);
    $this->session->received(RealtimeEvents::transcribed('item_2', 'Yes please.'));

    $outputs = $this->connection->ofType('conversation.item.create');
    expect($beforeYes)->toHaveCount(1)
        ->and($beforeYes[0]['item']['output'])->toBe(VoiceToolRunner::CONFIRM_FIRST_RESULT)
        ->and($beforeYes[0]['item']['id'])->toStartWith('tool_held_')
        ->and(array_last($outputs)['item'])->toMatchArray(['call_id' => 'fc_2', 'output' => 'Feedback sent.'])
        ->and(array_last($outputs)['item']['id'])->toStartWith('tool_done_');
    Event::assertDispatched(ToolCallCompleted::class, fn (ToolCallCompleted $event): bool => $event->approvalOutcome === 'approved'
        && $event->gated
        && $event->decidedAt !== null
        && $event->decisionNote === 'Yes please.');
});

it('drops the action when the answer was not a yes, audited as denied, or when the visitor talked over the question', function (): void {
    $this->dispatcher->shouldNotReceive('dispatch');
    $other = app(VoiceCallSession::class)->for(VoiceCalls::started(Conversation::factory()->forAgent($this->agent)->hostedChat()->create(), 'rtc_other', chatter: $this->chatter), $this->agent);
    $other->opened($otherConnection = new RecordingVoiceConnection);

    RealtimeEvents::visitorSays($this->session, 'item_1', 'Send Stripe a thank-you note.');
    $this->session->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));
    RealtimeEvents::visitorSays($this->session, 'item_2', 'No, leave it.');
    $this->session->received(RealtimeEvents::toolCall('resp_2', $this->tool, '{"feedback":"Thank you!"}', 'fc_2', 'item_2'));
    RealtimeEvents::visitorSays($other, 'item_1', 'Send Stripe a thank-you note.');
    $other->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));
    $other->received(RealtimeEvents::committed('item_2'));
    $other->received(RealtimeEvents::toolCall('resp_2', $this->tool, '{"feedback":"Thank you!"}', 'fc_2', 'item_2'));
    $other->received(RealtimeEvents::committed('item_3'));

    $declined = array_last($this->connection->ofType('conversation.item.create'))['item'];
    $talkedOver = array_last($otherConnection->ofType('conversation.item.create'))['item'];
    expect($declined)->toMatchArray(['call_id' => 'fc_2', 'output' => VoiceToolRunner::DECLINED_RESULT])
        ->and($declined['id'])->toStartWith('tool_cancelled_')
        ->and($talkedOver['call_id'])->toBe('fc_2')
        ->and($talkedOver['id'])->toStartWith('tool_cancelled_');
    Event::assertDispatched(ToolCallCompleted::class, fn (ToolCallCompleted $event): bool => $event->approvalOutcome === 'denied'
        && $event->gated
        && $event->decisionNote === 'No, leave it.');
});

it('never offers an anonymous visitor an action that needs a yes', function (): void {
    $this->dispatcher->shouldNotReceive('dispatch');
    $call = VoiceCalls::started(Conversation::factory()->forAgent($this->agent)->hostedChat()->create(), 'rtc_visitor');
    $session = app(VoiceCallSession::class)->for($call, $this->agent);
    $session->opened($connection = new RecordingVoiceConnection);

    RealtimeEvents::visitorSays($session, 'item_1', 'Send Stripe a thank-you note.');
    $session->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));
    RealtimeEvents::visitorSays($session, 'item_2', 'Yes, send it.');
    $session->received(RealtimeEvents::toolCall('resp_2', $this->tool, '{"feedback":"Thank you!"}', 'fc_2', 'item_2'));

    expect(array_column(array_column($connection->ofType('conversation.item.create'), 'item'), 'output'))
        ->each->toContain('is not one of the tools available to you');
});
