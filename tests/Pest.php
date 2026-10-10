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
        'slug' => Str::random(14),
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
        'slug' => Str::random(14),
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
