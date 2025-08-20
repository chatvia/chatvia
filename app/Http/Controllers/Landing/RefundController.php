<?php

namespace App\Http\Controllers\Landing;

use Illuminate\Http\Request;

class RefundController
{
    public function __invoke(Request $request)
    {
        return view('pages.refund');
    }
}
