<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Billing\Models\UserCredit;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Llm\Models\Llm;
use Domain\Membership\Team\Services\TeamService;
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
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    $priceId = 'price_test_pro_'.Str::random(6);
    config(['billing.packages.pro.stripe_monthly_price_id' => $priceId]);
    $owner->billing()->firstOrCreate([]);
    $owner->refresh();
    $owner->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
        'quantity' => 1,
    ]);
    $owner->refresh();

    UserCredit::factory()->create([
        'user_id' => $owner->id,
        'messages' => 4000,
        'started_at' => now()->subDay(),
        'ends_at' => now()->addDays(20),
    ]);

    $agent = Agent::create([
        'user_id' => $owner->id,
        'team_id' => $owner->primaryTeam()->id,
        'llm_id' => Llm::factory()->create()->id,
        'name' => 'Channel Bot',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'system_instructions' => 'You are a helpful assistant.',
        'is_public' => true,
    ]);

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
