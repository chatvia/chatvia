<?php

use App\Auth\Models\User;
use Domain\Agents\Actions\DetachAgentTemplateAction;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Support\Facades\Storage;

it('reads appearance, widget, models, tuning, prompt and starters from the attached template', function (): void {
    Storage::fake();
    $user = User::factory()->create();
    createTemplateLlms(['gpt-4o', 'claude-sonnet']);
    $prompt = $user->prompts()->create(['name' => 'P', 'prompt' => 'Template prompt body.']);

    $template = AgentTemplate::factory()->create([
        'user_id' => $user->id,
        'agent_prompt_id' => $prompt->id,
        'color_theme' => 'indigo',
        'text' => ['welcome_message' => 'Hello from template'],
        'starters' => ['Starter A?', 'Starter B?'],
        'models' => ['gpt-4o', 'claude-sonnet'],
        'llm_settings' => [
            'temperature' => 0.4,
            'top_k' => 7,
            'top_p' => 0.9,
            'presence_penalty' => 0.0,
            'frequency_penalty' => 0.0,
        ],
        'widget_theme_color' => 'rose',
        'widget_language' => 'en',
        'widget_sites' => ['acme.test'],
    ]);

    $agent = createTemplateAgent($user, ['agent_template_id' => $template->id]);

    expect($agent->settings('appearance')->get('color_theme'))->toBe('indigo')
        ->and($agent->settings('appearance')->get('welcome_message'))->toBe('Hello from template')
        ->and($agent->settings('widget')->get('theme_color'))->toBe('rose')
        ->and($agent->settings('widget')->get('language'))->toBe('en')
        ->and($agent->settings('widget')->get('sites'))->toBe(['acme.test'])
        ->and($agent->resolvedLlms()->pluck('external_name')->sort()->values()->all())->toBe(['claude-sonnet', 'gpt-4o'])
        ->and($agent->llmSettings()['temperature'])->toBe(0.4)
        ->and($agent->resolveSystemInstructions())->toBe('Template prompt body.')
        ->and($agent->resolvedStarters())->toBe(['Starter A?', 'Starter B?']);
});

it('surfaces the template avatar through Storage::url on read', function (): void {
    Storage::fake();
    $user = User::factory()->create();
    $template = AgentTemplate::factory()->create([
        'user_id' => $user->id,
        'agent_avatar_path' => "users/{$user->id}/templates/seed/avatar.png",
    ]);
    Storage::put($template->agent_avatar_path, 'x');

    $agent = createTemplateAgent($user, ['agent_template_id' => $template->id]);

    expect($agent->settings('appearance')->get('avatar'))->toBe(Storage::url($template->agent_avatar_path));
});

it('inherits is_public from the attached template and snapshots it on detach', function (): void {
    Storage::fake();
    $user = User::factory()->create();

    // Template is public; the agent's own column stays protected (false).
    $template = AgentTemplate::factory()->create([
        'user_id' => $user->id,
        'is_public' => true,
    ]);
    $agent = createTemplateAgent($user, [
        'agent_template_id' => $template->id,
        'is_public' => false,
    ]);

    // While attached, the agent reads the template's public flag.
    expect($agent->is_public)->toBeTrue();

    app(DetachAgentTemplateAction::class)->execute($agent);

    // The template later flips back to protected; the detached agent keeps the
    // value it had while attached (snapshotted, no longer following).
    $template->update(['is_public' => false]);

    expect(Agent::find($agent->id)->is_public)->toBeTrue();
});

it('stops reading through once detached', function (): void {
    Storage::fake();
    $user = User::factory()->create();
    createTemplateLlms(['gpt-4o']);
    $template = AgentTemplate::factory()->create([
        'user_id' => $user->id,
        'color_theme' => 'indigo',
        'starters' => ['From template?'],
        'models' => ['gpt-4o'],
        'llm_settings' => [
            'temperature' => 0.2,
            'top_k' => 0,
            'top_p' => 1.0,
            'presence_penalty' => 0.0,
            'frequency_penalty' => 0.0,
        ],
    ]);
    $agent = createTemplateAgent($user, ['agent_template_id' => $template->id]);

    app(DetachAgentTemplateAction::class)->execute($agent);

    // Edit the template AFTER detaching: the agent must no longer follow it.
    $template->update(['color_theme' => 'amber', 'starters' => ['Changed?']]);

    $fresh = Agent::find($agent->id);
    expect($fresh->agent_template_id)->toBeNull()
        ->and($fresh->settings('appearance')->get('color_theme'))->toBe('indigo')
        ->and($fresh->resolvedStarters())->toBe(['From template?']);
});
