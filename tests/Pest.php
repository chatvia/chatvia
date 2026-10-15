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

use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Domain\Agent\Models\Agent;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\LlmProviders;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\RefreshTestDatabases;
use Tests\Support\FakeLlmProviders;
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
 * Create a minimal agent in the user's workspace for unit tests, ensuring the
 * user has a primary team. Pass any column override via `$overrides`.
 *
 * @param  array<string, mixed>  $overrides
 */
function createAgent(User $user, array $overrides = []): Agent
{
    $team = $user->primaryTeam() ?? app(TeamService::class)->createDefaultTeam($user);

    return Agent::create(array_merge([
        'workspace_id' => $user->workspace()->id,
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
        'workspace_id' => $user->workspace()->id,
        'team_id' => $team->id,
        'name' => $name,
        'external_id' => Str::random(14),
        'slug' => Str::random(14),
        'is_public' => false,
        'source' => 'api',
    ]);
}

/**
 * A teammate of the owner's workspace on $team (the owner's primary team when
 * omitted), under a new role granting exactly $permissions. Their current team
 * is that team, so the permission helpers resolve it.
 *
 * @param  list<string>  $permissions
 * @param  list<string>  $agentExternalIds  the agents the role reaches when not $allAgents
 */
function teammateWithPermissions(User $owner, array $permissions, ?Team $team = null, bool $allAgents = true, array $agentExternalIds = []): User
{
    $team ??= $owner->primaryTeam() ?? app(TeamService::class)->createDefaultTeam($owner);

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(10),
        permissions: $permissions,
        allAgents: $allAgents,
        agentExternalIds: $agentExternalIds,
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member;
}

/**
 * Switch off the agent's workspace the way the admin panel does: deactivate
 * its owner, which also stops its agents ({@see WorkspaceActivation}).
 */
function deactivateWorkspaceOf(Agent $agent, string $reason = 'Unpaid invoices'): void
{
    app(DeactivateAccountAction::class)->execute(
        $agent->workspace->owner ?? throw new LogicException('A workspace always has an owner.'),
        $reason,
    );
}

/** Switch the agent's workspace back on, as the admin panel does. */
function reactivateWorkspaceOf(Agent $agent): void
{
    app(ReactivateAccountAction::class)->execute(
        $agent->workspace->owner ?? throw new LogicException('A workspace always has an owner.'),
    );
}

/**
 * Every call to any model goes to this fake client.
 */
function bindFakeLlmProvider(LlmProviderInterface $provider): void
{
    app()->instance(LlmProviders::class, new FakeLlmProviders($provider));
}

/**
 * Give AI provider accounts a test key in config (Settings → AI providers),
 * so their chat models and services can be called. With none named, every
 * account gets one.
 */
function connectAiProviders(AiProvider ...$providers): void
{
    foreach ($providers === [] ? AiProvider::cases() : $providers as $provider) {
        config([$provider->apiKeyConfig() => 'test-key']);
    }
}

/**
 * A catalog model on a new provider (an OpenAI-compatible test server unless
 * a kind is given). A provider with an account gets a test key in config, as
 * a connected one has.
 *
 * @param  array<string, mixed>  $attributes
 */
function testLlm(string $internalName = 'test-model', LlmProviderEnum $kind = LlmProviderEnum::CUSTOM, array $attributes = []): Llm
{
    if (($account = AiProvider::forChatKind($kind)) instanceof AiProvider) {
        connectAiProviders($account);
    }

    return Llm::factory()
        ->for(LlmProvider::factory()->kind($kind), 'provider')
        ->create(['internal_name' => $internalName, ...$attributes]);
}

/**
 * Stub the LLM provider's chat-completion HTTP call so analysis tests get a
 * deterministic assistant message instead of hitting a real model.
 */
function fakeLlmCompletion(string $content): void
{
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => $content]]],
        ]),
    ]);
}

/**
 * An id that is free in both `users` and `chatters`, so a test can create a
 * staff account and a chatter with the same id and prove the two never mix.
 * Taking the next value of both sequences means neither table can hold it.
 */
function sharedStaffAndChatterId(): int
{
    return (int) DB::scalar("select greatest(nextval(pg_get_serial_sequence('users', 'id')), nextval(pg_get_serial_sequence('chatters', 'id')))");
}
