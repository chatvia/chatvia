<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RequestDemoController
{
    public function __invoke(Request $request)
    {
        return view('landing.request-demo');
    }
}
