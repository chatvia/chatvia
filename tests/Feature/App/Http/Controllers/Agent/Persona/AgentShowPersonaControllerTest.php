<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Inertia\Testing\AssertableInertia;

/**
 * The Persona page shows the agent's instructions to anyone with agent
 * access, but the workspace's prompt library only to someone who may set the
 * agent up or manage the library.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    AgentPrompt::factory()->create(['workspace_id' => $this->owner->workspace()->id]);
});

it('sends the prompt library only to a teammate who may set the agent up or manage prompts', function (array $permissions, int $count): void {
    $this->actingAs(teammateWithPermissions($this->owner, $permissions))
        ->get(route('agents.persona', $this->agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('prompts', $count));
})->with([
    'agent access only' => [['agent.chat.view'], 0],
    'agent.setup' => [['agent.setup'], 1],
    'account.prompts' => [['account.prompts'], 1],
]);
