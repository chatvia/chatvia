<?php

use App\Admin\AdminGlobalSearchProvider;
use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Illuminate\Support\Facades\Auth;

/**
 * The admin panel's own search finds the Settings too, each find linked
 * to its section.
 */
it('adds the Settings to the panel\'s own search', function (): void {
    Auth::guard('admin')->login(adminUser());
    Filament::setCurrentPanel('admin');

    expect(Filament::getPanel('admin')->getGlobalSearchProvider())->toBeInstanceOf(AdminGlobalSearchProvider::class);

    $settings = app(AdminGlobalSearchProvider::class)->getResults('reranker')->getCategories()['Settings'];

    expect($settings[0])->toBeInstanceOf(GlobalSearchResult::class)
        ->and($settings[0]->title)->toBe('Reranker')
        ->and($settings[0]->url)->toBe(ManageAiModelSettings::getUrl().'#ai-models-reranker')
        ->and($settings[0]->details)->toBe(['Page' => 'AI models']);
});
