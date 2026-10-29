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
 * The webhook is public and unauthenticated, so its security is the test
 * surface: the {secret} segment routes to the agent and the request must carry
 * a Bot Framework JWT the verifier accepts for this bot's App ID and its
 * serviceUrl (the real JWT crypto is covered by TeamsActivityVerifierTest,
 * here it is stubbed);
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

it('dispatches a job for a message activity verified for this bot’s App ID, once per activity id', function (): void {
    postTeamsActivity($this->secret, teamsActivity(['id' => 'act-7']))
        ->assertOk()
        ->assertJson(['ok' => true]);
    postTeamsActivity($this->secret, teamsActivity(['id' => 'act-7']))->assertOk();

    expect($this->verifier->checkedAppId)->toBe($this->teamsBot->app_id);
    Bus::assertDispatchedTimes(HandleTeamsActivityJob::class, 1);

    Bus::assertDispatched(HandleTeamsActivityJob::class, fn (HandleTeamsActivityJob $job): bool => $job->activity->conversationId === 'a:1conversation'
        && $job->activity->text === 'hello agent'
        && $job->bot->is($this->teamsBot)
        && jobQueue($job) === 'messages');
});

it('ignores a request whose JWT the verifier rejects or was issued for another serviceUrl', function (): void {
    $this->verifier->valid = false;
    postTeamsActivity($this->secret, teamsActivity(['id' => 'act-1']))->assertOk();

    $this->verifier->valid = true;
    $this->verifier->claims = ['serviceurl' => 'https://attacker.example/'];
    postTeamsActivity($this->secret, teamsActivity(['id' => 'act-2']))->assertOk();

    Bus::assertNotDispatched(HandleTeamsActivityJob::class);
});

it('answers 200 without dispatching for an unknown or disabled bot, or an activity sent by a bot', function (): void {
    $foreignBot = teamsActivity(['id' => 'act-10', 'from' => ['id' => '28:other-bot', 'role' => 'bot']]);
    postTeamsActivity($this->secret, $foreignBot)->assertOk();

    $self = teamsActivity(['id' => 'act-11', 'from' => ['id' => '28:f5e1f9d2-1111-2222-3333-444455556666', 'name' => 'Bot']]);
    postTeamsActivity($this->secret, $self)->assertOk();

    postTeamsActivity(Str::random(48), teamsActivity(['id' => 'act-12']))->assertOk();

    $this->teamsBot->update(['is_enabled' => false]);
    postTeamsActivity($this->secret, teamsActivity(['id' => 'act-13']))->assertOk();

    Bus::assertNotDispatched(HandleTeamsActivityJob::class);
});
