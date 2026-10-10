<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\UserCreditSystem;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\RefreshTestDatabases;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshTestDatabases::class)
    ->in('Feature', 'Unit');

// Eval harnesses (e.g. tool-routing retrieval quality) need the full app + a
// transactional DB like Feature/Unit, but run the REAL embeddings provider and
// skip themselves without an API key — so they stay out of normal CI on their own.
pest()
    ->extend(TestCase::class)
    ->use(RefreshTestDatabases::class)
    ->in('Eval');

// Architecture tests are framework-free guardrails (e.g. "every Action has a
// test"). They don't need a TestCase or DB; binding them to the bare PHPUnit
// TestCase keeps them fast and avoids RefreshTestDatabases overhead.
pest()->in('Architecture');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * Create a minimal, fully-owned agent for unit tests, ensuring the owner has a
 * primary team. Pass any column override via `$overrides`.
 *
 * @param  array<string, mixed>  $overrides
 */
function createAgent(User $user, array $overrides = []): Agent
{
    $team = $user->primaryTeam() ?? app(TeamService::class)->createDefaultTeam($user);

    return Agent::create(array_merge([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => 'Bot',
        'external_id' => Str::random(20),
        'sub_domain' => Str::random(14),
        'is_public' => false,
        'source' => 'dashboard',
    ], $overrides));
}

function makeTeamAgent(User $user, string $name, ?Team $team = null): Agent
{
    $team ??= $user->primaryTeam() ?? app(TeamService::class)->createDefaultTeam($user);

    return Agent::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'name' => $name,
        'external_id' => Str::random(14),
        'sub_domain' => Str::random(14),
        'is_public' => false,
        'source' => 'api',
    ]);
}

/**
 * Stub the message-credit send gate (UserCreditSystem::canSendMessages) so
 * billing-gated paths (evals, channel replies) can be tested without seeding
 * subscriptions and credit rows. Charging stays observable via Bus::fake().
 */
function fakeMessageCreditGate(bool $canSend = true): void
{
    $mock = Mockery::mock(UserCreditSystem::class);
    $mock->shouldReceive('forWorkspace')->andReturnSelf();
    $mock->shouldReceive('canSendMessages')->andReturn($canSend);

    app()->instance(UserCreditSystem::class, $mock);
}

/**
 * Stub the LLM provider's chat-completion HTTP call so analysis tests get a
 * deterministic assistant message instead of hitting a real model.
 */
function fakeLlmCompletion(string $content): void
{
    // The LLM provider builds an Authorization header from its (typed string)
    // API key before the request is made, so give it a value even though the
    // HTTP call itself is faked.
    config(['services.scalewayai.api_key' => 'test-key']);

    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => $content]]],
        ]),
    ]);
}

/**
 * Create an agent whose owning workspace is on the Pro plan — which grants the
 * topics, tone and sentiment capabilities. Mirrors the subscription seeding
 * used by the other capability-gated action tests.
 */
function proPlanAgent(): Agent
{
    $priceId = 'price_test_pro_'.Str::random(6);
    updateBillingPlan('pro', ['stripe_monthly_price_id' => $priceId]);

    $agent = Agent::factory()->create();

    // Registration is trial-checkout-only now (the Stripe webhook provisions
    // billing), so factory users have no UserBilling row — create it explicitly.
    $agent->user->billing()->firstOrCreate([]);

    $agent->user->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
        'quantity' => 1,
    ]);
    $agent->user->refresh();

    return $agent;
}

/**
 * Change one of the migrated plans (`starter`, `standard`, `pro`,
 * `enterprise`) for a test, e.g. its Stripe price ids. Saved through Eloquent,
 * so the billing catalog cache is cleared as it is in the admin panel.
 *
 * @param  array<string, mixed>  $attributes
 */
function updateBillingPlan(string $key, array $attributes): BillingPlan
{
    $plan = BillingPlan::query()->where('key', $key)->firstOrFail();
    $plan->update($attributes);

    return $plan;
}

/**
 * Change one of the migrated add-ons (keyed by its enum value) for a test.
 *
 * @param  array<string, mixed>  $attributes
 */
function updateBillingAddon(string $key, array $attributes): BillingAddon
{
    $addon = BillingAddon::query()->where('key', $key)->firstOrFail();
    $addon->update($attributes);

    return $addon;
}

/**
 * A plan's fixed (cycle-independent) limits, as `user_quotas` columns, for
 * seeding a quota row.
 *
 * @return array<string, int>
 */
function planFixedLimits(string $key = 'starter'): array
{
    return BillingPlan::query()->where('key', $key)->firstOrFail()->quota_limits['fixed'];
}
