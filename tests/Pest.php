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

use App\Auth\Models\User;
use App\Teams\Services\TeamService;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Llm;
use Illuminate\Support\Str;
use Tests\RefreshTestDatabases;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshTestDatabases::class)
    ->in('Feature', 'Unit');

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
 * Create a minimal, fully-owned agent for the template tests, ensuring the
 * owner has a primary team. Pass `agent_template_id` (or any column) via
 * `$overrides`.
 *
 * @param  array<string, mixed>  $overrides
 */
function createTemplateAgent(User $user, array $overrides = []): Agent
{
    if (! $user->primaryTeam()) {
        app(TeamService::class)->createDefaultTeam($user);
    }

    return Agent::create(array_merge([
        'user_id' => $user->id,
        'team_id' => $user->primaryTeam()->id,
        'name' => 'Bot',
        'type' => 'knowledge_base',
        'external_id' => Str::random(20),
        'sub_domain' => Str::random(14),
        'is_public' => false,
        'source' => 'dashboard',
    ], $overrides));
}

/**
 * Seed LLM catalog rows whose `external_name` matches what a template's
 * `models` list references (and what `models.* exists:llms,external_name`
 * validates against). Returns the external names for convenience.
 *
 * @param  array<int, string>  $externalNames
 * @return array<int, string>
 */
function createTemplateLlms(array $externalNames = ['gpt-4o', 'claude-sonnet']): array
{
    foreach ($externalNames as $name) {
        Llm::factory()->create([
            'internal_name' => $name,
            'external_name' => $name,
        ]);
    }

    return $externalNames;
}

/**
 * Named-argument array shared by CreateTemplateData / UpdateTemplateData (sans
 * `user`, which only CreateTemplateData needs). Keys match the DTO constructor
 * parameter names so it can be splatted directly into the constructor.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function templateDataArgs(array $overrides = []): array
{
    return array_merge([
        'name' => 'Support',
        'type' => 'knowledge_base',
        'agentPromptId' => null,
        'colorTheme' => 'indigo',
        'colorShade' => 500,
        'text' => [
            'welcome_message' => 'Hi from template',
            'chat_window_title' => 'Help',
            'chat_footer_text' => 'Footer',
            'composer_placeholder' => 'Type here',
        ],
        'starters' => ['One?', 'Two?'],
        'models' => ['gpt-4o'],
        'llmSettings' => [
            'temperature' => 0.3,
            'top_k' => 5,
            'top_p' => 0.8,
            'presence_penalty' => 0.1,
            'frequency_penalty' => 0.2,
        ],
        'avatar' => null,
        'logo' => null,
        'widgetThemeColor' => 'rose',
        'widgetThemeShade' => 600,
        'widgetPosition' => 'bottom-left',
        'widgetWindowSize' => 'small',
        'widgetShowOnLoad' => true,
        'widgetShowOnLoadDelay' => 1200,
        'widgetSites' => ['acme.test'],
        'widgetBubbles' => ['Hello!'],
        'widgetLanguage' => 'en',
        'isPublic' => false,
    ], $overrides);
}
