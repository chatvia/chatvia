<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Queries\Platform\PlatformChannelQuery;
use Domain\Analytics\Services\AnalyticsDatabase;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\Analytics\PlatformScopes;

/**
 * The admin dashboard's channel split: the conversations started in the
 * window per channel, busiest first, from the per-conversation metrics. Runs
 * in a future month no seeded row falls in.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
});

function pcqConversation(Agent $agent, ?ConversationSource $source, string $startedAt): void
{
    app(AnalyticsDatabase::class)->table('analytics_conversation_metrics')->insert([
        'conversation_external_id' => (string) Str::uuid(),
        'agent_id' => $agent->id,
        'workspace_id' => $agent->workspace_id,
        'started_at' => $startedAt,
        'source' => $source?->value,
    ]);
}

it('counts conversations per channel, busiest first, within the window and the workspace', function (): void {
    $acme = User::factory()->workspace()->create();
    $acmeBot = createAgent($acme);
    $globexBot = createAgent(User::factory()->workspace()->create());

    pcqConversation($acmeBot, ConversationSource::Widget, '2031-03-10 10:00:00');
    pcqConversation($acmeBot, ConversationSource::Widget, '2031-03-11 10:00:00');
    pcqConversation($acmeBot, ConversationSource::Whatsapp, '2031-03-12 10:00:00');
    // Recorded before the channel was: no channel to count it under.
    pcqConversation($acmeBot, null, '2031-03-12 12:00:00');
    pcqConversation($acmeBot, ConversationSource::Telegram, '2031-02-12 10:00:00');
    pcqConversation($globexBot, ConversationSource::Slack, '2031-03-12 10:00:00');

    $acmeOnly = app(PlatformChannelQuery::class)->get(PlatformScopes::of(workspaceId: $acme->workspace()->id));

    expect($acmeOnly->totalConversations)->toBe(3)
        ->and(collect($acmeOnly->channels)->pluck('label')->all())->toBe(['Chat Widget', 'WhatsApp'])
        ->and($acmeOnly->channels[0]->conversations)->toBe(2)
        ->and($acmeOnly->channels[0]->pct)->toBe(66.7)
        ->and(app(PlatformChannelQuery::class)->get(PlatformScopes::of())->totalConversations)->toBe(4);
});
