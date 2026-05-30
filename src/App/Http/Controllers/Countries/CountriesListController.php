<?php

namespace App\Http\Controllers\Countries;

use App\Country\Models\Country;
use Illuminate\Http\Request;

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
