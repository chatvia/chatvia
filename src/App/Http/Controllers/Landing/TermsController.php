<?php

namespace App\Http\Controllers\Landing;

use Illuminate\Http\Request;

class TermsController
{
    public function __invoke(Request $request)
    {
        return view('pages.terms');
    }
}
