<?php

namespace App\Http\Controllers\Settings\Usage;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsUsageController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('settings/Usage');
    }
}
