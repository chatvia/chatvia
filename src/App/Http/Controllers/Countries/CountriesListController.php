<?php

namespace App\Http\Controllers\Countries;

use Illuminate\Http\Request;
use Support\Localization\Country\Models\Country;

class CountriesListController
{
    public function __invoke(Request $request)
    {
        return Country::query()
            ->select('code', 'name')->orderBy('name')
            ->get()
            ->map(function (Country $country) {
                return [
                    'code' => $country->code,
                    'name' => __($country->name),
                ];
            });
    }
}
