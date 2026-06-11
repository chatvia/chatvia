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

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

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
    config(['billing.packages.pro.stripe_monthly_price_id' => $priceId]);

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
