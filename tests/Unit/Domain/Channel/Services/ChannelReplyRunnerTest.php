<?php

use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Domain\Llm\Models\Llm;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * One messaging-channel turn through the real chat pipeline, with a scripted
 * model. The runner mirrors the per-turn gates of ChatTurnStreamer (its
 * ChatTurnStreamer*Test files are the web side): a deactivated workspace, a
 * reached message limit, no model, and the visitor's and the agent's rate
 * limits each answer with a fixed reply and run nothing. It also strips the
 * web-only `<render-form …>` and `<render-calendar …>` tags from a reply.
 */
beforeEach(function (): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Happy to help.'));
});

/** A public agent with a model whose guardrails are off, so the scripted answer comes back as written. */
function crrAgent(): Agent
{
    $agent = Agent::factory()->create(['is_public' => true, 'llm_id' => testLlm()->id]);
    $agent->setSetting('enabled', false, 'guardrails');

    return $agent->fresh();
}

/** The agent's turn in a Telegram chat, resolved first, as the channel job does. */
function crrTurn(Agent $agent, string $message = 'I would like a demo'): ChannelTurnData
{
    $chat = app(TelegramChatResolver::class)->resolve(
        AgentTelegramBot::factory()->forAgent($agent)->create(),
        '555',
        new TelegramSenderData('Sam'),
    );

    return new ChannelTurnData(
        agent: $agent,
        sessionExternalId: $chat->sessionExternalId(),
        conversationExternalId: $chat->conversationExternalId(),
        message: $message,
        source: ConversationSource::Telegram,
        requestIdPrefix: 'tg_',
    );
}

it('answers with the model’s reply, stripped of web-only render tags', function (string $answer, string $reply): void {
    bindFakeLlmProvider(ScriptedLlmProvider::answering($answer));

    expect(app(ChannelReplyRunner::class)->reply(crrTurn(crrAgent())))->toBe($reply);
})->with([
    'a plain answer' => ['Happy to help.', 'Happy to help.'],
    'a self-closing calendar tag, and the gap it leaves' => [
        "Pick a time.\n\n<render-calendar meeting_type=\"intro\"/>\n\nSee you soon.",
        "Pick a time.\n\nSee you soon.",
    ],
    'a paired form tag with inner content' => [
        'Please fill this: <render-form slug="lead-capture">fallback</render-form> thanks!',
        'Please fill this:  thanks!',
    ],
]);

it('answers with a fixed reply and runs no turn while a gate is shut', function (Closure $shut, string $reply): void {
    $agent = crrAgent();
    $turn = crrTurn($agent);
    $shut($agent, $turn);
    $messagesBefore = Message::query()->count();

    expect(app(ChannelReplyRunner::class)->reply($turn))->toBe($reply)
        ->and(Message::query()->count())->toBe($messagesBefore);
})->with([
    'the workspace is deactivated' => [
        function (Agent $agent): void {
            deactivateWorkspaceOf($agent);
        },
        'Sorry — this assistant is unavailable right now. Please try again later.',
    ],
    'the workspace reached its monthly limit' => [
        fn (Agent $agent): MessageLimit => MessageLimit::factory()->forWorkspace($agent->workspace)->reached()->create(),
        'Sorry — this assistant is unavailable right now. Please try again later.',
    ],
    'the sender is over their own per-minute cap' => [
        function (Agent $agent, ChannelTurnData $turn): void {
            $agent->setSetting(key: 'visitor_rate_limit_per_minute', value: 1, group: 'security');
            app(AgentChatRateLimiter::class)->visitorExceeded($agent, $turn->sessionExternalId);
        },
        'You are sending messages too quickly. Please wait a moment and try again.',
    ],
    'the agent is over its per-minute cap' => [
        function (Agent $agent): void {
            $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');
            app(AgentChatRateLimiter::class)->exceeded($agent);
        },
        'This assistant is receiving too many messages right now. Please try again in a moment.',
    ],
]);

it('answers that the assistant is not set up when no model can answer', function (): void {
    Llm::query()->update(['is_enabled' => false]);
    Llm::flushCatalogCache();
    $agent = Agent::factory()->create(['is_public' => true]);

    expect(app(ChannelReplyRunner::class)->reply(crrTurn($agent)))->toBe('Sorry — this assistant is not fully configured yet.');
});
