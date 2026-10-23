<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramChatResolver;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Support\Str;
use Spectra\Spectra;
use Tests\Support\Llm\ScriptedLlmProvider;

/**
 * A messaging-channel turn tags the AI calls Spectra records with the
 * conversation the channel resolver minted, so channel traffic lands on the
 * same Trackables aggregation as the web surfaces.
 */
function crrstTurn(): int
{
    bindFakeLlmProvider(ScriptedLlmProvider::answering('Happy to help.'));

    $agent = Agent::factory()->create(['is_public' => true, 'llm_id' => testLlm()->id]);
    $agent->setSetting('enabled', false, 'guardrails');
    $bot = AgentTelegramBot::create([
        'agent_id' => $agent->id,
        'bot_token' => '123:token',
        'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48),
        'is_enabled' => true,
    ]);
    $chat = app(TelegramChatResolver::class)->resolve($bot, '555', new TelegramSenderData('Sam'));

    app(ChannelReplyRunner::class)->reply(new ChannelTurnData(
        agent: $agent->fresh(),
        sessionExternalId: $chat->sessionExternalId(),
        conversationExternalId: $chat->conversationExternalId(),
        message: 'I would like a demo',
        source: ConversationSource::Telegram,
        requestIdPrefix: 'tg_',
    ));

    return $chat->chat_conversation_id;
}

it('tags the channel turn with its conversation while Spectra records', function (): void {
    config(['spectra.enabled' => true]);

    $conversationId = crrstTurn();

    $request = app(Spectra::class)->startRequest('openai', 'gpt-test');
    expect($request->trackableType)->toBe('conversation')
        ->and($request->trackableId)->toBe($conversationId);
});

it('tags nothing while Spectra is not recording', function (): void {
    config(['spectra.enabled' => false]);

    crrstTurn();

    expect(app(Spectra::class)->startRequest('openai', 'gpt-test')->trackableType)->toBeNull();
});
