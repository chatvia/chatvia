<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RefundController
{
    public function __invoke(Request $request)
    {
        return view('pages.refund');
    }
}
