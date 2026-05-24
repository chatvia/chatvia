<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SupportController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('Support');
    }
}
