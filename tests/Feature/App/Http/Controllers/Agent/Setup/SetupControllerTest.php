<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Inertia\Testing\AssertableInertia;

/**
 * Setup → Model sends the workspace's prompt library only to someone who may
 * set the agent up or manage the library.
 */
it('sends the prompt library only to someone who may set the agent up or manage prompts', function (array $permissions, int $count): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    AgentPrompt::factory()->create(['workspace_id' => $owner->workspace()->id]);

    $this->actingAs(teammateWithPermissions($owner, $permissions))
        ->get(route('agents.setup', $agent->external_id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('prompts', $count));
})->with([
    'agent access only' => [['agent.chat.view'], 0],
    'agent.setup' => [['agent.setup'], 1],
    'account.prompts' => [['account.prompts'], 1],
]);
