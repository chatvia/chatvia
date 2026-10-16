<?php

use App\Admin\Settings\Actions\ReloadSavedSettingsAction;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\SettingsConfig;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\DB;

/**
 * ReloadSavedSettingsAction: the settings and the AI model list are read from
 * the database again, so a value saved while the cache held an older copy
 * reaches config and the model pickers.
 */
it('puts a key the cache missed into config', function (): void {
    app(AiProviderSettings::class)->toArray();

    DB::table('settings')
        ->where('group', AiProviderSettings::group())
        ->where('name', 'openrouter_api_key')
        ->update(['payload' => json_encode(encrypt('sk-or-reloaded'))]);

    app(SettingsConfig::class)->apply();

    expect(config('services.openrouter.api_key'))->not->toBe('sk-or-reloaded');

    app(ReloadSavedSettingsAction::class)->execute();

    expect(config('services.openrouter.api_key'))->toBe('sk-or-reloaded')
        ->and(app(AiProviderSettings::class)->openrouter_api_key)->toBe('sk-or-reloaded');
});

it('reads the AI model list again', function (): void {
    Llm::catalog();

    $model = Llm::withoutEvents(fn (): Llm => Llm::factory()->create());

    expect(Llm::catalog()->pluck('id'))->not->toContain($model->id);

    app(ReloadSavedSettingsAction::class)->execute();

    expect(Llm::catalog()->pluck('id'))->toContain($model->id);
});
