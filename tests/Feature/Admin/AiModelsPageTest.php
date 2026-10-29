<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\Actions\RemoveLlmModelAction;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Models\Llm;
use Filament\Actions\Testing\TestAction;

/**
 * Settings → AI models: a chat model is removed with its agents and the
 * tasks that used it moved to another model, and the last model agents use
 * cannot be removed.
 */
beforeEach(function (): void {
    $this->admin = adminUser();

    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

it('removes a model, moving its agents and the tasks that used it', function (): void {
    $old = testLlm('old-model');
    $new = testLlm('new-model');
    $agent = createAgent($this->admin, ['llm_id' => $old->id]);
    $settings = app(AiSettings::class);
    $settings->default_agent_model = (string) $old->id;
    $settings->task_model = (string) $old->id;
    $settings->titles_model = (string) $old->id;
    $settings->images_model = (string) $new->id;
    $settings->save();

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->callAction(TestAction::make('removeModel')->table($old), data: ['replacement' => $new->id])
        ->assertHasNoActionErrors();

    expect($agent->fresh()->llm_id)->toBe($new->id)
        ->and($old->fresh()->is_enabled)->toBeFalse()
        ->and(savedSetting(AiSettings::class, 'default_agent_model'))->toBe((string) $new->id)
        ->and(savedSetting(AiSettings::class, 'task_model'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'titles_model'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'images_model'))->toBe((string) $new->id);
});

it('refuses to remove the last model agents use and keeps it on', function (): void {
    Llm::query()->update(['is_enabled' => false]);
    $only = testLlm('only-model');
    createAgent($this->admin, ['llm_id' => $only->id]);

    adminPage(ManageAiModelSettings::class, $this->admin)->assertActionDisabled(TestAction::make('removeModel')->table($only));

    expect(fn () => app(RemoveLlmModelAction::class)->execute($only))->toThrow(InvalidArgumentException::class)
        ->and($only->fresh()->is_enabled)->toBeTrue();
});
