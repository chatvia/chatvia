<?php

use Domain\Channel\Teams\Jobs\HandleTeamsActivityJob;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsActivityVerifier;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Channel\Teams\FakeTeamsActivityVerifier;

/**
 * The webhook is public and unauthenticated, so its security IS the test
 * surface: the {secret} segment routes to the agent AND the request must carry
 * a Bot Framework JWT the verifier accepts for this bot's App ID (the real
 * JWT crypto is covered by TeamsActivityVerifierTest — here it is stubbed);
 * activities are processed once; bot/self activities never dispatch (or the
 * bot could answer itself in a loop); and it always answers 200.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->secret = Str::random(48);
    $this->teamsBot = AgentTeamsBot::factory()->create(['app_id' => 'f5e1f9d2-1111-2222-3333-444455556666', 'webhook_secret' => $this->secret]);

    $this->verifier = new FakeTeamsActivityVerifier;
    $this->app->instance(TeamsActivityVerifier::class, $this->verifier);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function teamsActivity(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'message',
        'id' => 'act-100',
        'text' => 'hello agent',
        'serviceUrl' => 'https://smba.trafficmanager.net/emea/',
        'conversation' => ['id' => 'a:1conversation', 'conversationType' => 'personal'],
        'from' => ['id' => '29:user-aad', 'name' => 'Lina'],
        'recipient' => ['id' => '28:f5e1f9d2-1111-2222-3333-444455556666'],
    ], $overrides);
}

function postTeamsActivity(string $urlSecret, array $payload): TestResponse
{
    return test()->postJson(
        route('msteams.webhook', ['secret' => $urlSecret]),
        $payload,
        ['Authorization' => 'Bearer connector-jwt'],
    );
}

it('dispatches a job for a verified message activity', function (): void {
    postTeamsActivity($this->secret, teamsActivity())
        ->assertOk()
        ->assertJson(['ok' => true]);

    Bus::assertDispatched(HandleTeamsActivityJob::class, fn (HandleTeamsActivityJob $job): bool => $job->activity['conversation']['id'] === 'a:1conversation'
        && $job->activity['text'] === 'hello agent'
        && $job->bot->is($this->teamsBot)
        && jobQueue($job) === 'messages');
});

it('verifies the JWT against this bot’s App ID', function (): void {
    postTeamsActivity($this->secret, teamsActivity())->assertOk();

    expect($this->verifier->checkedAppId)->toBe($this->teamsBot->app_id);
});

it('ignores a request whose JWT the verifier rejects', function (): void {
    $this->verifier->valid = false;

    postTeamsActivity($this->secret, teamsActivity())->assertOk();

    Bus::assertNotDispatched(HandleTeamsActivityJob::class);
});

it('ignores an activity whose serviceUrl is not the one the token was issued for', function (): void {
    // A token replayed with another serviceUrl must not send the reply elsewhere.
    $this->verifier->claims = ['serviceurl' => 'https://attacker.example/'];

    postTeamsActivity($this->secret, teamsActivity())->assertOk();

    Bus::assertNotDispatched(HandleTeamsActivityJob::class);
});

it('answers 200 without dispatching when no enabled bot has the webhook secret', function (Closure $miss): void {
    postTeamsActivity($miss->call($this), teamsActivity())->assertOk();

    Bus::assertNotDispatched(HandleTeamsActivityJob::class);
})->with([
    'an unknown secret' => [fn (): string => Str::random(48)],
    'a disabled bot' => [function (): string {
        $this->teamsBot->update(['is_enabled' => false]);

        return $this->secret;
    }],
]);

it('processes each activity id at most once', function (): void {
    postTeamsActivity($this->secret, teamsActivity(['id' => 'act-7']))->assertOk();
    postTeamsActivity($this->secret, teamsActivity(['id' => 'act-7']))->assertOk();

    Bus::assertDispatchedTimes(HandleTeamsActivityJob::class, 1);
});

it('ignores non-message activities (conversation updates, reactions)', function (): void {
    postTeamsActivity($this->secret, teamsActivity(['type' => 'conversationUpdate', 'text' => null]))->assertOk();
    postTeamsActivity($this->secret, teamsActivity(['type' => 'messageReaction', 'id' => 'act-101']))->assertOk();

    Bus::assertNotDispatched(HandleTeamsActivityJob::class);
});

it('ignores empty or missing text', function (): void {
    postTeamsActivity($this->secret, teamsActivity(['text' => '   ']))->assertOk();

    Bus::assertNotDispatched(HandleTeamsActivityJob::class);
});

it('ignores activities sent by bots — including its own replies echoing back', function (): void {
    $foreignBot = teamsActivity(['id' => 'act-10', 'from' => ['id' => '28:other-bot', 'role' => 'bot']]);
    postTeamsActivity($this->secret, $foreignBot)->assertOk();

    $self = teamsActivity(['id' => 'act-11', 'from' => ['id' => '28:f5e1f9d2-1111-2222-3333-444455556666', 'name' => 'Bot']]);
    postTeamsActivity($this->secret, $self)->assertOk();

    Bus::assertNotDispatched(HandleTeamsActivityJob::class);
});
