<?php

namespace App\Http\Controllers\Landing;

use Illuminate\Http\Request;

class PrivacyController
{
    public function __invoke(Request $request)
    {
        return view('pages.policy');
    }
}
