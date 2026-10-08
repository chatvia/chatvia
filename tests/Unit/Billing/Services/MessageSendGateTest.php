<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Billing\Enums\MessageAdmission;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Services\MessageSendGate;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Data\StreamRequestMessageData;
use Domain\Llm\Models\Llm;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One send gate for every surface. The web chat stream and the messaging
 * channels used to carry their own copies with different admin behaviour
 * (channels let admins through without any check). The rule now lives in
 * MessageSendGate, and both surfaces must refuse the same owner.
 */
beforeEach(fn () => Cache::flush());

function sendGateOwner(bool $admin = false, bool $subscribed = true, int $credits = 100): User
{
    $owner = ($admin ? User::factory()->admin() : User::factory()->workspace())->create();
    $billing = $owner->billing()->firstOrCreate([]);

    if ($subscribed) {
        $billing->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_gate_'.Str::random(8),
            'stripe_status' => 'active',
            'stripe_price' => 'price_gate_test',
            'quantity' => 1,
        ]);
    }

    if ($credits > 0) {
        UserCredit::factory()->create([
            'user_id' => $owner->id,
            'messages' => $credits,
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(20),
        ]);
    }

    return $owner->fresh();
}

function sendGateAgent(User $owner): Agent
{
    app(TeamService::class)->createDefaultTeam($owner);

    return Agent::create([
        'user_id' => $owner->id,
        'team_id' => $owner->fresh()->primaryTeam()->id,
        'llm_id' => Llm::factory()->create()->id,
        'name' => 'Gate Bot',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'system_instructions' => 'You are a helpful assistant.',
        'is_public' => true,
    ]);
}

/**
 * The SSE error code the web stream would send, read from the response's
 * event generator (pumping the real stream would close the test's output
 * buffers).
 */
function webStreamErrorCode(StreamedResponse $response): ?string
{
    $events = (new ReflectionFunction($response->getCallback()))->getClosureUsedVariables()['events'];

    foreach ($events as $event) {
        $payload = json_decode(substr($event->toLine(), 6, -2), true);

        if (($payload['type'] ?? null) === 'error') {
            return $payload['data']['code'];
        }
    }

    return null;
}

it('admits a subscribed workspace with credits left', function (): void {
    expect(app(MessageSendGate::class)->admit(sendGateOwner()))->toBe(MessageAdmission::Allowed);
});

it('refuses a workspace without an active subscription even while the capacity flag is warm', function (): void {
    $owner = sendGateOwner(subscribed: false);
    app(UserCreditSystem::class)->forWorkspace($owner->id)->setCanSendMessages(true);

    expect(app(MessageSendGate::class)->admit($owner))->toBe(MessageAdmission::SubscriptionInactive);
});

it('refuses a subscribed workspace that has no message credits left', function (): void {
    expect(app(MessageSendGate::class)->admit(sendGateOwner(credits: 0)))->toBe(MessageAdmission::CreditsExhausted);
});

it('lets an admin skip the subscription check but not the capacity check', function (): void {
    // Warm flag (e.g. credits granted manually): the admin is admitted.
    $warm = sendGateOwner(admin: true, subscribed: false);
    app(UserCreditSystem::class)->forWorkspace($warm->id)->setCanSendMessages(true);

    expect(app(MessageSendGate::class)->admit($warm))->toBe(MessageAdmission::Allowed);

    // Recomputed flag: no subscription and no credits means no capacity.
    $cold = sendGateOwner(admin: true, subscribed: false, credits: 0);

    expect(app(MessageSendGate::class)->admit($cold))->toBe(MessageAdmission::CreditsExhausted);
});

it('refuses the same admin owner on the web stream and on a messaging channel', function (): void {
    // Before the shared gate, channels returned early for admins and ran the
    // full pipeline without checking credits at all.
    $owner = sendGateOwner(admin: true, subscribed: false, credits: 0);
    $agent = sendGateAgent($owner);

    $web = app(ChatStreamService::class)->stream(new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: $agent->llm, message: 'hi'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
    ));

    expect(webStreamErrorCode($web))->toBe('message_limit_reached');

    // No LLM is faked: if the gate let the turn through, the pipeline would
    // fail and the runner would answer "something went wrong" instead.
    $channel = app(ChannelReplyRunner::class)->reply(
        agent: $agent->fresh(),
        sessionExternalId: (string) Str::uuid(),
        conversationExternalId: (string) Str::uuid(),
        message: 'hi',
        source: 'telegram',
        requestIdPrefix: 'tg_',
    );

    expect($channel)->toBe('Sorry — this assistant is unavailable right now. Please try again later.');
});

it('reports an expired subscription on the web stream with its own error code', function (): void {
    $owner = sendGateOwner(subscribed: false);
    $agent = sendGateAgent($owner);

    $web = app(ChatStreamService::class)->stream(new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: $agent->llm, message: 'hi'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
    ));

    expect(webStreamErrorCode($web))->toBe('subscription_expired');
});

it('answers a refused channel turn in the language the owner pinned for the agent', function (): void {
    $owner = sendGateOwner(subscribed: false);
    $agent = sendGateAgent($owner);
    $agent->setSetting(key: 'language', value: 'de', group: 'embed');

    $reply = app(ChannelReplyRunner::class)->reply(
        agent: $agent->fresh(),
        sessionExternalId: (string) Str::uuid(),
        conversationExternalId: (string) Str::uuid(),
        message: 'hi',
        source: 'telegram',
        requestIdPrefix: 'tg_',
    );

    expect($reply)->toBe('Entschuldigung – dieser Assistent ist gerade nicht verfügbar. Bitte versuchen Sie es später erneut.');
});
