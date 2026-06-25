<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Data\StreamRequestMessageData;
use Domain\Form\Models\FormDefinition;
use Domain\Llm\Models\Llm;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

/**
 * buildSystemPrompt is private — it's the only producer of the system message,
 * so reach it via reflection to cover the chatter-identity gating directly.
 */
function systemPromptFor(Agent $agent, ?string $chatterName, ?string $source = null): string
{
    $data = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: Llm::factory()->create(), message: 'Hi'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
        chatterName: $chatterName,
        source: $source,
    );

    $service = app(ChatService::class);
    $method = (new ReflectionClass(ChatService::class))->getMethod('buildSystemPrompt');
    $method->setAccessible(true);

    return $method->invoke($service, $data)['content'];
}

it('tells a protected agent the signed-in chatter name', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    expect(systemPromptFor($agent, 'Ada Lovelace'))->toContain('Ada Lovelace');
});

it('does not name the visitor on a public agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    expect(systemPromptFor($agent, 'Ada Lovelace'))->not->toContain('Ada Lovelace');
});

it('omits the identity block when no chatter is signed in', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    expect(systemPromptFor($agent, null))->not->toContain('signed-in user');
});

function formsAgent(): Agent
{
    $user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($user);

    return makeTeamAgent($user, 'Bot');
}

it('lists active forms (slug + description) when the Forms capability is enabled', function (): void {
    $agent = formsAgent();
    $server = $agent->user->mcpServers()->create([
        'connector_slug' => 'forms', 'category' => 'builtin', 'name' => 'Forms',
        'slug' => 'forms', 'url' => config('app.url').'/mcp/forms', 'auth_type' => 'none', 'is_enabled' => true,
    ]);
    $agent->mcpServers()->attach($server->id);

    FormDefinition::factory()->forAgent($agent)->create([
        'name' => 'Book a demo',
        'slug' => 'book-demo',
        'description' => 'Show when the visitor wants to schedule a demo.',
    ]);

    expect(systemPromptFor($agent, null))
        ->toContain('book-demo')
        ->toContain('Show when the visitor wants to schedule a demo');
});

it('omits the forms block when the Forms capability is not enabled', function (): void {
    $agent = formsAgent();
    FormDefinition::factory()->forAgent($agent)->create(['slug' => 'book-demo']);

    expect(systemPromptFor($agent, null))->not->toContain('book-demo');
});

it('omits the forms block on messaging channels — they cannot render the inline form', function (): void {
    $agent = formsAgent();
    $server = $agent->user->mcpServers()->create([
        'connector_slug' => 'forms', 'category' => 'builtin', 'name' => 'Forms',
        'slug' => 'forms', 'url' => config('app.url').'/mcp/forms', 'auth_type' => 'none', 'is_enabled' => true,
    ]);
    $agent->mcpServers()->attach($server->id);
    FormDefinition::factory()->forAgent($agent)->create(['slug' => 'book-demo']);

    // Web surfaces still get it; only the text-only channels are excluded.
    expect(systemPromptFor($agent, null, 'widget'))->toContain('book-demo');
    expect(systemPromptFor($agent, null, 'telegram'))->not->toContain('book-demo');
    expect(systemPromptFor($agent, null, 'slack'))->not->toContain('book-demo');
});
