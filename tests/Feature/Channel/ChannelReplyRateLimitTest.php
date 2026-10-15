<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Messaging channels reach the chat pipeline through {@see ChannelReplyRunner},
 * not the web SSE surface — so the per-agent rate limit (enforced in
 * ChatStreamService on the web) has to be re-applied here too. Without it a
 * visitor could send unlimited messages via Telegram/Slack/etc.
 */
beforeEach(function (): void {
    Cache::flush();
});

function rateLimitedChannelAgent(): Agent
{
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id, 'is_public' => true]);

    $agent->setSetting(key: 'chat_rate_limit_per_minute', value: 1, group: 'security');

    return $agent;
}

it('refuses a channel turn once the agent is over its per-minute cap', function (): void {
    $agent = rateLimitedChannelAgent();

    // Fill the bucket so the very next turn is over the cap — the gate then
    // short-circuits before the chat pipeline runs. No LLM is bound, so if the
    // gate were bypassed the pipeline would throw and the runner would return
    // its generic "something went wrong" message instead of the throttle copy.
    RateLimiter::hit('agent-chat-rate:'.$agent->id, 60);

    $reply = app(ChannelReplyRunner::class)->reply(new ChannelTurnData(
        agent: $agent,
        sessionExternalId: (string) Str::uuid(),
        conversationExternalId: (string) Str::uuid(),
        message: 'spam',
        source: ConversationSource::Telegram,
        requestIdPrefix: 'tg_',
    ));

    expect($reply)->toContain('too many messages');
});
