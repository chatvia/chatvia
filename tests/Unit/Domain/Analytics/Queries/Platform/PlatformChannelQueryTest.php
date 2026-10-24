<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Queries\Platform\PlatformChannelQuery;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Carbon;
use Tests\Support\Analytics\PlatformScopes;

/**
 * The admin dashboard's channel split: the window's conversations per channel,
 * busiest first, without deleted or hidden ones. Runs in a future month no
 * seeded row falls in.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
});

function pcqConversation(Agent $agent, ConversationSource $source, string $createdAt, bool $visible = true): Conversation
{
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'source' => $source, 'is_visible' => $visible]);
    $conversation->forceFill(['created_at' => $createdAt])->saveQuietly();

    return $conversation;
}

it('counts conversations per channel, busiest first, skipping deleted and hidden ones', function (): void {
    $acme = User::factory()->workspace()->create();
    $acmeBot = createAgent($acme);
    $globexBot = createAgent(User::factory()->workspace()->create());

    pcqConversation($acmeBot, ConversationSource::Widget, '2031-03-10 10:00:00');
    pcqConversation($acmeBot, ConversationSource::Widget, '2031-03-11 10:00:00');
    pcqConversation($acmeBot, ConversationSource::Whatsapp, '2031-03-12 10:00:00');
    pcqConversation($acmeBot, ConversationSource::Whatsapp, '2031-03-12 11:00:00')->delete();
    pcqConversation($acmeBot, ConversationSource::Whatsapp, '2031-03-12 12:00:00', visible: false);
    pcqConversation($acmeBot, ConversationSource::Telegram, '2031-02-12 10:00:00');
    pcqConversation($globexBot, ConversationSource::Slack, '2031-03-12 10:00:00');

    $acmeOnly = app(PlatformChannelQuery::class)->get(PlatformScopes::of(workspaceId: $acme->workspace()->id));

    expect($acmeOnly->totalConversations)->toBe(3)
        ->and(collect($acmeOnly->channels)->pluck('label')->all())->toBe(['Chat Widget', 'WhatsApp'])
        ->and($acmeOnly->channels[0]->conversations)->toBe(2)
        ->and($acmeOnly->channels[0]->pct)->toBe(66.7)
        ->and(app(PlatformChannelQuery::class)->get(PlatformScopes::of())->totalConversations)->toBe(4);
});
