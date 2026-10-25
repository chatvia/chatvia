<?php

declare(strict_types=1);

use App\Admin\Settings\AiProviderIcon;
use App\Admin\Settings\OptionLabel;
use Domain\Llm\Enums\AiProvider;
use Filament\Support\Icons\Heroicon;

/**
 * A service picker's choice: a provider with its own logo, or a choice that
 * is no provider with an icon, before the name.
 */
it('puts a provider\'s logo before its name', function (): void {
    $label = OptionLabel::provider('Cohere <Rerank>', AiProvider::Cohere);

    expect($label)->toStartWith('<span class="cv-model-label">')
        ->toContain('src="'.AiProviderIcon::of(AiProvider::Cohere).'"', 'alt=""', '<span>Cohere &lt;Rerank&gt;</span>');
});

it('puts an icon before a choice that is no provider', function (): void {
    expect(OptionLabel::icon('Off', Heroicon::OutlinedNoSymbol))
        ->toStartWith('<span class="cv-model-label">')
        ->toContain('<svg', 'fi-size-sm', '<span>Off</span>');
});
