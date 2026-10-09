<?php

declare(strict_types=1);

namespace App\Http\Controllers\Countries;

use Support\Localization\Country\Data\CountryOptionData;
use Support\Localization\Country\Models\Country;

/**
 * Every country (ISO code and translated name), sorted by name, for the
 * country pickers in the dashboard.
 */
class CountriesListController
{
    /** @return list<CountryOptionData> */
    public function __invoke(): array
    {
        return Country::options();
    }
}
