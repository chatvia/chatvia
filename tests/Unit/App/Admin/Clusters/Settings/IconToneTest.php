<?php

declare(strict_types=1);

use App\Admin\Clusters\Settings\IconTone;
use Filament\Support\Icons\Heroicon;

/*
 * The colour of an icon's tile in Settings follows the icon, so the same
 * icon looks the same on every page; a logo keeps its own colours.
 */

it('gives an icon the same tone in any of its styles', function (): void {
    expect(IconTone::of(Heroicon::OutlinedSparkles))->toBe('violet')
        ->and(IconTone::of(Heroicon::Sparkles))->toBe('violet')
        ->and(IconTone::cssClass(Heroicon::OutlinedEnvelope))->toBe('cv-tone-rose');
});

it('keeps a logo in its own colours, and makes anything else grey', function (): void {
    expect(IconTone::of('https://chatvia.test/images/llms/gpt.svg'))->toBe(IconTone::LOGO)
        ->and(IconTone::of(Heroicon::OutlinedFaceSmile))->toBe('slate')
        ->and(IconTone::of('heroicon-o-star'))->toBe('slate')
        ->and(IconTone::of(null))->toBe('slate');
});
