<?php

use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;

/**
 * Messaging channels check the same workspace message limit as the web chat:
 * over the limit, the runner answers with its fixed "unavailable" reply and
 * never runs the chat pipeline (no LLM is bound here, so running it would
 * fail with the generic error reply instead).
 */
it('answers with the unavailable reply when the workspace reached its monthly limit', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id, 'is_public' => true]);
    MessageLimit::create([
        'user_id' => $agent->user_id,
        'monthly_limit' => 1,
        'messages_used' => 1,
        'period_starts_at' => now()->startOfMonth(),
    ]);

    $reply = app(ChannelReplyRunner::class)->reply(new ChannelTurnData(
        agent: $agent,
        sessionExternalId: (string) Str::uuid(),
        conversationExternalId: (string) Str::uuid(),
        message: 'hello',
        source: ConversationSource::Telegram,
        requestIdPrefix: 'tg_',
    ));

    expect($reply)->toContain('this assistant is unavailable right now');
});
