<?php

namespace App\Http\Controllers\Countries;

use Illuminate\Support\Collection;
use Support\Localization\Country\Models\Country;

/**
 * Every country (ISO code and translated name), sorted by name, for the
 * country pickers in the dashboard.
 */
class CountriesListController
{
    /**
     * @return Collection<int, array{code: string, name: array<array-key, mixed>|string}>
     */
    public function __invoke(): Collection
    {
        return Country::query()
            ->select('code', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn (Country $country): array => [
                'code' => $country->code,
                'name' => __($country->name),
            ]);
    }
}
