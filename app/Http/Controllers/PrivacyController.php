<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PrivacyController
{
    public function __invoke(Request $request)
    {
        return view('pages.policy');
    }
}
