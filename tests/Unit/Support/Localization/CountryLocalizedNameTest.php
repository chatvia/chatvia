<?php

use Illuminate\Support\Facades\App;
use Support\Localization\Country\Data\CountryOptionData;
use Support\Localization\Country\Models\Country;

/*
 * Country names come from ICU (PHP intl), so every UI language gets them
 * without one translation key per country. The stored English name is only
 * the fallback for a code ICU does not know.
 */

it('names a country in the app locale', function (): void {
    $country = new Country()->forceFill(['code' => 'de', 'name' => 'Germany']);

    App::setLocale('de');
    expect($country->localizedName())->toBe('Deutschland');

    App::setLocale('ar');
    expect($country->localizedName())->toBe('ألمانيا');

    expect($country->localizedName('en'))->toBe('Germany');
});

it('falls back to the stored name for a code ICU does not know', function (): void {
    $country = new Country()->forceFill(['code' => 'xx', 'name' => 'Nowhere']);

    expect($country->localizedName('fr'))->toBe('Nowhere');
});

it('lists the picker options sorted by the localized name', function (): void {
    App::setLocale('de');

    $options = Country::options();
    $names = array_map(fn (CountryOptionData $option): string => $option->name, $options);

    expect($options)->not->toBeEmpty()
        ->and($names)->toContain('Deutschland')
        ->and($names)->toContain('Österreich');

    // German collation puts "Österreich" among the O's, before "Polen".
    expect(array_search('Österreich', $names, true))->toBeLessThan(array_search('Polen', $names, true));
});
