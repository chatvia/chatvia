<?php

use App\Auth\Models\User;
use Domain\Agents\Actions\DetachAgentTemplateAction;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Support\Facades\Storage;

it('copies the full template config onto the agent and clears the link', function (): void {
    Storage::fake();
    $user = User::factory()->create();
    createTemplateLlms(['gpt-4o', 'claude-sonnet']);
    $prompt = $user->prompts()->create(['name' => 'P', 'prompt' => 'You are helpful.']);

    $template = AgentTemplate::factory()->create([
        'user_id' => $user->id,
        'agent_prompt_id' => $prompt->id,
        'color_theme' => 'indigo',
        'color_shade' => 500,
        'text' => [
            'welcome_message' => 'Hi from template',
            'chat_window_title' => 'Help',
            'chat_footer_text' => 'Footer',
            'composer_placeholder' => 'Type here',
        ],
        'starters' => ['One?', 'Two?'],
        'models' => ['gpt-4o', 'claude-sonnet'],
        'llm_settings' => [
            'temperature' => 0.3,
            'top_k' => 5,
            'top_p' => 0.8,
            'presence_penalty' => 0.1,
            'frequency_penalty' => 0.2,
        ],
        'agent_avatar_path' => "users/{$user->id}/templates/seed/avatar.png",
        'logo_image_path' => "users/{$user->id}/templates/seed/logo.png",
        'widget_theme_color' => 'rose',
        'widget_theme_shade' => 600,
        'widget_position' => 'bottom-left',
        'widget_window_size' => 'small',
        'widget_show_on_load' => true,
        'widget_show_on_load_delay' => 1200,
        'widget_sites' => ['acme.test'],
        'widget_bubbles' => ['Hello!'],
        'widget_language' => 'en',
    ]);

    // The template's stored images must exist for Storage::copy to succeed.
    Storage::put($template->agent_avatar_path, 'fake-avatar');
    Storage::put($template->logo_image_path, 'fake-logo');

    $agent = createTemplateAgent($user, ['agent_template_id' => $template->id]);

    app(DetachAgentTemplateAction::class)->execute($agent);

    $fresh = $agent->fresh();

    // Link cleared; prompt copied onto the agent.
    expect($fresh->agent_template_id)->toBeNull()
        ->and($fresh->agent_prompt_id)->toBe($prompt->id);

    // Appearance copied (now read from the agent's own model_settings).
    $appearance = $fresh->settings('appearance');
    expect($appearance->get('color_theme'))->toBe('indigo')
        ->and($appearance->get('color_shade'))->toBe(500)
        ->and($appearance->get('welcome_message'))->toBe('Hi from template')
        ->and($appearance->get('composer_placeholder'))->toBe('Type here');

    // Avatar/logo copied to NEW per-agent paths (not the template's path).
    $avatarRow = $fresh->modelSettings()->where('group', 'appearance')->where('key', 'avatar')->first();
    $logoRow = $fresh->modelSettings()->where('group', 'appearance')->where('key', 'logo')->first();
    expect($avatarRow->value)->not->toBe($template->agent_avatar_path)
        ->and($logoRow->value)->not->toBe($template->logo_image_path);
    Storage::assertExists($avatarRow->value);
    Storage::assertExists($logoRow->value);

    // Widget color + behavior copied (incl. allowed sites and language).
    $widget = $fresh->settings('widget');
    expect($widget->get('theme_color'))->toBe('rose')
        ->and($widget->get('language'))->toBe('en')
        ->and($widget->get('sites'))->toBe(['acme.test'])
        ->and($widget->get('show_on_load'))->toBeTrue();

    // Starters copied.
    expect($fresh->starterSet->text)->toBe(['One?', 'Two?']);

    // Models synced and tuning applied to the agent's own pivot.
    expect($fresh->llms()->pluck('external_name')->sort()->values()->all())
        ->toBe(['claude-sonnet', 'gpt-4o']);

    $settings = $fresh->llmSettings();
    expect((float) $settings['temperature'])->toEqualWithDelta(0.3, 0.0001)
        ->and((int) $settings['top_k'])->toBe(5)
        ->and((float) $settings['top_p'])->toEqualWithDelta(0.8, 0.0001);
});

it('does nothing when no template is attached', function (): void {
    $agent = createTemplateAgent(User::factory()->create());

    app(DetachAgentTemplateAction::class)->execute($agent);

    expect($agent->fresh()->agent_template_id)->toBeNull()
        ->and($agent->modelSettings()->count())->toBe(0);
});
