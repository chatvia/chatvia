<?php

use App\Auth\Models\User;
use Domain\Agents\Actions\AttachAgentTemplateAction;
use Domain\Agents\Models\AgentStarter;
use Domain\Agents\Models\AgentTemplate;

it('attaches a template by setting the foreign key', function (): void {
    $user = User::factory()->create();
    $agent = createTemplateAgent($user);
    $template = AgentTemplate::factory()->create(['user_id' => $user->id]);

    app(AttachAgentTemplateAction::class)->execute($agent, $template);

    expect($agent->fresh()->agent_template_id)->toBe($template->id);
});

it('copies nothing on attach (pure read-through)', function (): void {
    $user = User::factory()->create();
    $agent = createTemplateAgent($user);
    $template = AgentTemplate::factory()->create([
        'user_id' => $user->id,
        'starters' => ['hi'],
        'color_theme' => 'indigo',
    ]);

    app(AttachAgentTemplateAction::class)->execute($agent, $template);

    expect($agent->modelSettings()->count())->toBe(0)
        ->and(AgentStarter::where('agent_id', $agent->id)->exists())->toBeFalse()
        ->and($agent->llms()->exists())->toBeFalse();
});

it('rejects a template owned by a different user', function (): void {
    $agent = createTemplateAgent(User::factory()->create());
    $template = AgentTemplate::factory()->create(['user_id' => User::factory()->create()->id]);

    app(AttachAgentTemplateAction::class)->execute($agent, $template);
})->throws(InvalidArgumentException::class);

it('rejects a template whose type does not match the agent', function (): void {
    $user = User::factory()->create();
    $agent = createTemplateAgent($user); // knowledge_base
    $template = AgentTemplate::factory()->widget()->create(['user_id' => $user->id]);

    app(AttachAgentTemplateAction::class)->execute($agent, $template);
})->throws(InvalidArgumentException::class);
