<?php

use App\Auth\Models\User;
use Domain\Agents\Actions\DeleteTemplateAction;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Support\Facades\Storage;

it('deletes the template and detaches attached agents, copying config', function (): void {
    Storage::fake();
    $user = User::factory()->create();
    createTemplateLlms(['gpt-4o']);

    $template = AgentTemplate::factory()->create([
        'user_id' => $user->id,
        'starters' => ['Hello?'],
        'models' => ['gpt-4o'],
        'llm_settings' => [
            'temperature' => 0.5,
            'top_k' => 0,
            'top_p' => 1.0,
            'presence_penalty' => 0.0,
            'frequency_penalty' => 0.0,
        ],
    ]);
    $agent = createTemplateAgent($user, ['agent_template_id' => $template->id]);

    app(DeleteTemplateAction::class)->execute($user, $template->external_id);

    expect(AgentTemplate::whereKey($template->id)->exists())->toBeFalse()
        ->and($agent->fresh()->agent_template_id)->toBeNull()
        ->and($agent->fresh()->starterSet->text)->toBe(['Hello?']);
});

it('leaves templates owned by another user untouched', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $template = AgentTemplate::factory()->create(['user_id' => $owner->id]);

    app(DeleteTemplateAction::class)->execute($other, $template->external_id);

    expect(AgentTemplate::whereKey($template->id)->exists())->toBeTrue();
});
