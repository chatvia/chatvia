<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Services\WidgetAuthSessionService;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Widget\WidgetHeaders;
use Illuminate\Http\Request;

/**
 * On a protected widget the visitor session belongs to the signed-in chatter.
 * The stream checks that owner too, so a chatter who replays someone else's
 * session id cannot send messages in that person's conversations.
 */
beforeEach(function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);

    $this->agent = Agent::factory()->create(['is_public' => false]);
    $this->agent->setSetting('sites', ['customer.example'], 'widget');

    $this->jane = Chatter::factory()->create(['workspace_id' => $this->agent->workspace_id]);
    $this->omar = Chatter::factory()->create(['workspace_id' => $this->agent->workspace_id]);
});

/**
 * Widget headers of a signed-in chatter: a widget session token as the auth
 * exchange mints it, bound to the test client's IP and user agent.
 *
 * @param  array<string, string>  $extra
 * @return array<string, string>
 */
function signedInWidgetHeaders(Agent $agent, Chatter $chatter, array $extra = []): array
{
    $client = Request::create('/widget', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'Widget test']);

    return [
        WidgetHeaders::AGENT_ID => $agent->external_id,
        WidgetHeaders::AUTH_TOKEN => app(WidgetAuthSessionService::class)->issue($chatter, $agent, $client)['token'],
        'Origin' => 'https://customer.example',
        'User-Agent' => 'Widget test',
        ...$extra,
    ];
}

/**
 * Boots a widget session as the chatter.
 *
 * @return array{session_id: string, conversation_id: string}
 */
function bootSignedInWidget(Agent $agent, Chatter $chatter): array
{
    return test()->withHeaders(signedInWidgetHeaders($agent, $chatter))
        ->postJson('/widget/sessions/init')
        ->assertSuccessful()
        ->json();
}

it('streams on the chatter\'s own session', function (): void {
    $boot = bootSignedInWidget($this->agent, $this->jane);

    expect(Session::query()->where('external_id', $boot['session_id'])->value('chatter_id'))->toBe($this->jane->id);

    $this->withHeaders(signedInWidgetHeaders($this->agent, $this->jane, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->post('/widget/stream', ['content' => 'Hello'], ['Accept' => 'text/event-stream'])
        ->assertOk();
});

it('refuses to stream on the session of another chatter', function (): void {
    $boot = bootSignedInWidget($this->agent, $this->jane);
    $messagesBefore = Message::query()->count();

    $this->withHeaders(signedInWidgetHeaders($this->agent, $this->omar, [
        WidgetHeaders::SESSION_ID => $boot['session_id'],
        WidgetHeaders::CONVERSATION_ID => $boot['conversation_id'],
    ]))
        ->postJson('/widget/stream', ['content' => 'Hello'])
        ->assertForbidden();

    expect(Message::query()->count())->toBe($messagesBefore);
});
