<?php

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Enums\LlmTask;

/**
 * The Models for app tasks card of Settings → AI models: a model for quick
 * tasks and one per app task, each picked from the models that may be used.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('picks the model of each app task from the models that may be used', function (string $property, string $configKey): void {
    $picked = testLlm('picked-model');
    $off = testLlm('off-model', attributes: ['is_enabled' => false]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([$property => (string) $off->id])
        ->call('save')
        ->assertHasFormErrors([$property]);

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->fillForm([$property => (string) $picked->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, $property))->toBe((string) $picked->id)
        ->and(config($configKey))->toBe((string) $picked->id);
})->with([
    'the model for app tasks' => ['task_model', 'chatvia.ai.tasks.default'],
    'one task' => ['guardrails_model', 'chatvia.ai.tasks.guardrails'],
]);

it('has a setting and a field for every app task, image descriptions on the Documents card', function (): void {
    $page = adminPage(ManageAiModelSettings::class, $this->admin);

    foreach (LlmTask::appTasks() as $task) {
        expect(AiSettings::propertyFor($task->configKey()))->toBe($task->settingsProperty());

        $page->assertFormFieldExists($task->settingsProperty());
    }
});

it('warns about a model its provider no longer serves and keeps it out of the pickers', function (): void {
    $gone = testLlm('gone-model', attributes: ['unlisted_at' => now()]);
    testLlm('kept-model');

    adminPage(ManageAiModelSettings::class, $this->admin)
        ->assertSee('gone-model ('.$gone->provider->name.') is no longer served')
        ->fillForm(['guardrails_model' => (string) $gone->id])
        ->call('save')
        ->assertHasFormErrors(['guardrails_model']);
});
