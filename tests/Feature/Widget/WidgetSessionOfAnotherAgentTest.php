<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\WidgetHeaders;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * A session id belongs to one agent. Replayed through another agent's
 * widget, it must show none of that session's conversations, and "New chat"
 * must not start a conversation in it.
 */
it('lists nothing and starts nothing for a session of another agent', function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);

    $agent = Agent::factory()->create(['is_public' => true]);
    $agent->setSetting('sites', ['customer.example'], 'widget');

    $other = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $other->id]);
    Conversation::factory()->withTitle('Private thread')->create(['chat_session_id' => $session->id, 'is_visible' => true]);

    $this->withHeaders([
        WidgetHeaders::AGENT_ID => $agent->external_id,
        WidgetHeaders::SESSION_ID => $session->external_id,
        'Origin' => 'https://customer.example',
    ])
        ->getJson('/widget/conversations')
        ->assertOk()
        ->assertExactJson(['conversations' => []]);

    expect(Conversation::query()->where('chat_session_id', $session->id)->count())->toBe(1)
        ->and(Session::query()->where('external_id', $session->external_id)->count())->toBe(1);
});

it('starts no conversation in a session of another agent', function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);

    $agent = Agent::factory()->create(['is_public' => true]);
    $agent->setSetting('sites', ['customer.example'], 'widget');
    $session = Session::factory()->create(['agent_id' => Agent::factory()->create()->id]);

    $this->withHeaders([
        WidgetHeaders::AGENT_ID => $agent->external_id,
        WidgetHeaders::SESSION_ID => $session->external_id,
        'Origin' => 'https://customer.example',
    ])
        ->postJson('/widget/conversations')
        ->assertNotFound();

    expect(Conversation::query()->where('chat_session_id', $session->id)->count())->toBe(0);
});

it('starts a conversation in a new session id or the visitor\'s own session', function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);

    $agent = Agent::factory()->create(['is_public' => true]);
    $agent->setSetting('sites', ['customer.example'], 'widget');
    $own = Session::factory()->create(['agent_id' => $agent->id]);

    foreach ([(string) Str::uuid(), $own->external_id] as $sessionId) {
        $id = $this->withHeaders([
            WidgetHeaders::AGENT_ID => $agent->external_id,
            WidgetHeaders::SESSION_ID => $sessionId,
            'Origin' => 'https://customer.example',
        ])
            ->postJson('/widget/conversations')
            ->assertSuccessful()
            ->json('id');

        expect(Conversation::query()->where('external_id', $id)->first()?->session?->external_id)->toBe($sessionId);
    }
});

it('starts no conversation in another chatter\'s session on a protected agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    EmbeddedWidget::allowSite($agent);
    $jane = Chatter::factory()->grantedTo($agent)->create();
    $omar = Chatter::factory()->grantedTo($agent)->create();
    $janeSession = EmbeddedWidget::bootSignedIn($agent, $jane)['session_id'];
    $before = Conversation::query()->whereHas('session', fn (Builder $query): Builder => $query->where('external_id', $janeSession))->count();

    $this->withHeaders(EmbeddedWidget::signedInHeaders($agent, $omar, [WidgetHeaders::SESSION_ID => $janeSession]))
        ->postJson('/widget/conversations')
        ->assertNotFound();

    expect(Conversation::query()->whereHas('session', fn (Builder $query): Builder => $query->where('external_id', $janeSession))->count())->toBe($before);
});
