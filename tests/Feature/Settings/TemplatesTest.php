<?php

use App\Auth\Models\User;
use App\Teams\Services\TeamService;
use Domain\Agents\Models\AgentTemplate;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    createTemplateLlms(['gpt-4o']);
});

function validTemplatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Support template',
        'type' => 'knowledge_base',
        'prompt_id' => null,
        'color_theme' => 'indigo',
        'color_shade' => 500,
        'text' => [
            'welcome_message' => 'Hi!',
            'chat_window_title' => 'Help',
            'chat_footer_text' => 'Footer',
            'composer_placeholder' => 'Type...',
        ],
        'starters' => ['First?'],
        'models' => ['gpt-4o'],
        'llm_settings' => [
            'temperature' => 0.7,
            'top_k' => 10,
            'top_p' => 0.9,
            'presence_penalty' => 0.1,
            'frequency_penalty' => 0.2,
        ],
        'widget_theme_color' => 'rose',
        'widget_theme_shade' => 600,
        'widget_position' => 'bottom-right',
        'widget_window_size' => 'large',
        'widget_show_on_load' => false,
        'widget_show_on_load_delay' => 500,
        'widget_sites' => ['example.com'],
        'widget_bubbles' => [],
        'widget_language' => 'en',
    ], $overrides);
}

it('creates a template', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.templates.store'), validTemplatePayload())
        ->assertRedirect(route('settings.templates.index'));

    expect($this->user->templates()->where('name', 'Support template')->exists())->toBeTrue();
});

it('persists the is_public flag from the request', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.templates.store'), validTemplatePayload(['name' => 'Public tpl', 'is_public' => true]))
        ->assertRedirect(route('settings.templates.index'));

    expect($this->user->templates()->where('name', 'Public tpl')->value('is_public'))->toBeTrue();
});

it('creates a widget template with a single model', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.templates.store'), validTemplatePayload(['type' => 'widget', 'models' => ['gpt-4o']]))
        ->assertRedirect(route('settings.templates.index'));

    expect($this->user->templates()->where('type', 'widget')->exists())->toBeTrue();
});

it('rejects more than one model for a widget template', function (): void {
    createTemplateLlms(['claude-sonnet']);

    $this->actingAs($this->user)
        ->post(route('settings.templates.store'), validTemplatePayload(['type' => 'widget', 'models' => ['gpt-4o', 'claude-sonnet']]))
        ->assertSessionHasErrors('models');
});

it('rejects an invalid type', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.templates.store'), validTemplatePayload(['type' => 'search']))
        ->assertSessionHasErrors('type');
});

it('keeps the type locked on update', function (): void {
    $template = \Domain\Agents\Models\AgentTemplate::factory()->widget()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->put(
            route('settings.templates.update', ['external_id' => $template->external_id]),
            validTemplatePayload(['type' => 'knowledge_base', 'models' => ['gpt-4o']]),
        )
        ->assertRedirect(route('settings.templates.index'));

    expect($template->fresh()->type->value)->toBe('widget');
});

it('rejects an empty models list', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.templates.store'), validTemplatePayload(['models' => []]))
        ->assertSessionHasErrors('models');
});

it('rejects a model that is not in the catalog', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.templates.store'), validTemplatePayload(['models' => ['nope-model']]))
        ->assertSessionHasErrors('models.0');
});

it('rejects a duplicate name', function (): void {
    AgentTemplate::factory()->create(['user_id' => $this->user->id, 'name' => 'Taken']);

    $this->actingAs($this->user)
        ->post(route('settings.templates.store'), validTemplatePayload(['name' => 'Taken']))
        ->assertSessionHasErrors('name');
});

it('rejects a prompt owned by another user', function (): void {
    $other = User::factory()->create();
    $prompt = $other->prompts()->create(['name' => 'Theirs', 'prompt' => 'x']);

    $this->actingAs($this->user)
        ->post(route('settings.templates.store'), validTemplatePayload(['prompt_id' => $prompt->external_id]))
        ->assertSessionHasErrors('prompt_id');
});

it('rejects an unsupported widget language', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.templates.store'), validTemplatePayload(['widget_language' => 'zz-not-real']))
        ->assertSessionHasErrors('widget_language');
});

it('updates a template', function (): void {
    $template = AgentTemplate::factory()->create(['user_id' => $this->user->id, 'name' => 'Old']);

    $this->actingAs($this->user)
        ->put(route('settings.templates.update', ['external_id' => $template->external_id]), validTemplatePayload(['name' => 'Renamed']))
        ->assertRedirect(route('settings.templates.index'));

    expect($template->fresh()->name)->toBe('Renamed');
});

it('cannot update a template owned by another user', function (): void {
    $other = User::factory()->create();
    $template = AgentTemplate::factory()->create(['user_id' => $other->id]);

    $this->actingAs($this->user)
        ->put(route('settings.templates.update', ['external_id' => $template->external_id]), validTemplatePayload())
        ->assertForbidden();
});

it('deletes a template', function (): void {
    $template = AgentTemplate::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->delete(route('settings.templates.destroy', ['external_id' => $template->external_id]))
        ->assertRedirect();

    expect(AgentTemplate::whereKey($template->id)->exists())->toBeFalse();
});
