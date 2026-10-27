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
 * the model ask first, runs it when the model repeats it after the visitor's
 * spoken yes (audited with their words), waits for the yes to be written
 * down, and tells the call view how each held action ended. Which repeat
 * counts as a yes is VoiceToolApprovals' rule.
 */
beforeEach(function (): void {
    Queue::fake();
    Event::fake([AssistantReplyCompleted::class, ToolCallCompleted::class]);
    app()->instance(RealtimeCalls::class, new ScriptedRealtimeCalls);

    $this->agent = Agent::factory()->create(['is_public' => true]);
    // Guardrails off, so the real guards return the words unchanged without a classifier call.
    $this->agent->setSetting('enabled', false, 'guardrails');
    // Only a signed-in chatter (or staff) is offered actions that need a yes.
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

it('tells the call view a held action is not done yet, and when a run failed', function (): void {
    $this->dispatcher->shouldReceive('dispatch')->once()->andReturn(ToolDispatchResultData::error('Stripe is down.'));

    RealtimeEvents::visitorSays($this->session, 'item_1', 'Send Stripe a thank-you note.');
    $this->session->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));
    RealtimeEvents::visitorSays($this->session, 'item_2', 'Yes, send it.');
    $this->session->received(RealtimeEvents::toolCall('resp_2', $this->tool, '{"feedback":"Thank you!"}', 'fc_2', 'item_2'));

    [$held, $failed] = array_column($this->connection->ofType('conversation.item.create'), 'item');

    expect($held['id'])->toStartWith('tool_held_')
        ->and($failed['id'])->toStartWith('tool_failed_');
});

it('answers a waiting yes the visitor talked over as cancelled', function (): void {
    $this->dispatcher->shouldNotReceive('dispatch');

    RealtimeEvents::visitorSays($this->session, 'item_1', 'Send Stripe a thank-you note.');
    $this->session->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));
    $this->session->received(RealtimeEvents::committed('item_2'));
    $this->session->received(RealtimeEvents::toolCall('resp_2', $this->tool, '{"feedback":"Thank you!"}', 'fc_2', 'item_2'));
    $this->session->received(RealtimeEvents::committed('item_3'));

    $output = array_last($this->connection->ofType('conversation.item.create'))['item'];

    expect($output['call_id'])->toBe('fc_2')
        ->and($output['id'])->toStartWith('tool_cancelled_');
});

it('holds the action and has the model ask first', function (): void {
    $this->dispatcher->shouldNotReceive('dispatch');

    RealtimeEvents::visitorSays($this->session, 'item_1', 'Send Stripe a thank-you note.');
    $this->session->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));

    expect($this->connection->ofType('conversation.item.create')[0]['item']['output'])->toBe(VoiceToolRunner::CONFIRM_FIRST_RESULT)
        // No "let me check" before a question: the model goes straight on to ask.
        ->and(array_column($this->connection->ofType('response.create'), 'response'))->toBe([
            ['metadata' => ['turn' => 'item_1']],
            ['metadata' => ['turn' => 'item_1']],
        ]);
    Event::assertNotDispatched(ToolCallCompleted::class);
});

it('runs it when the model repeats it after the visitor\'s yes, and audits their words', function (): void {
    $this->dispatcher->shouldReceive('dispatch')->once()->andReturn(ToolDispatchResultData::text('Feedback sent.'));

    RealtimeEvents::visitorSays($this->session, 'item_1', 'Send Stripe a thank-you note.');
    $this->session->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));
    RealtimeEvents::visitorSays($this->session, 'item_2', 'Yes, send it.');
    // The same arguments, however the model spaces them.
    $this->session->received(RealtimeEvents::toolCall('resp_2', $this->tool, '{ "feedback": "Thank you!" }', 'fc_2', 'item_2'));

    $outputs = $this->connection->ofType('conversation.item.create');
    expect(array_last($outputs)['item'])->toMatchArray(['call_id' => 'fc_2', 'output' => 'Feedback sent.'])
        ->and(array_last($outputs)['item']['id'])->toStartWith('tool_done_');
    Event::assertDispatched(ToolCallCompleted::class, fn (ToolCallCompleted $event): bool => $event->approvalOutcome === 'approved'
        && $event->gated
        && $event->decidedAt !== null
        && $event->decisionNote === 'Yes, send it.');
});

it('waits for the yes to be written down before it runs', function (): void {
    $this->dispatcher->shouldReceive('dispatch')->once()->andReturn(ToolDispatchResultData::text('Feedback sent.'));

    RealtimeEvents::visitorSays($this->session, 'item_1', 'Send Stripe a thank-you note.');
    $this->session->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));
    $this->session->received(RealtimeEvents::committed('item_2'));
    $this->session->received(RealtimeEvents::toolCall('resp_2', $this->tool, '{"feedback":"Thank you!"}', 'fc_2', 'item_2'));

    expect($this->connection->ofType('conversation.item.create'))->toHaveCount(1);

    $this->session->received(RealtimeEvents::transcribed('item_2', 'Yes please.'));

    expect(array_last($this->connection->ofType('conversation.item.create'))['item'])->toMatchArray(['call_id' => 'fc_2', 'output' => 'Feedback sent.']);
});

it('asks again when the answer could not be written down', function (): void {
    $this->dispatcher->shouldNotReceive('dispatch');

    RealtimeEvents::visitorSays($this->session, 'item_1', 'Send Stripe a thank-you note.');
    $this->session->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));
    $this->session->received(RealtimeEvents::committed('item_2'));
    $this->session->received(RealtimeEvents::toolCall('resp_2', $this->tool, '{"feedback":"Thank you!"}', 'fc_2', 'item_2'));

    $this->travel(6)->seconds();
    $this->session->tick();

    expect(array_last($this->connection->ofType('conversation.item.create'))['item'])
        ->toMatchArray(['call_id' => 'fc_2', 'output' => VoiceToolRunner::CONFIRM_FIRST_RESULT]);
});

it('lets the model act on the answer instead of forcing a search', function (): void {
    VoiceCalls::giveAgentKnowledge($this->agent);
    $session = app(VoiceCallSession::class)->for($this->call, $this->agent);
    $session->opened($connection = new RecordingVoiceConnection);

    RealtimeEvents::visitorSays($session, 'item_1', 'Send Stripe a thank-you note.');
    $session->received(RealtimeEvents::toolCall('resp_1', $this->tool, '{"feedback":"Thank you!"}'));
    $session->received(RealtimeEvents::committed('item_2'));

    expect(array_last($connection->ofType('response.create'))['response'])->toBe(['metadata' => ['turn' => 'item_2']]);
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
