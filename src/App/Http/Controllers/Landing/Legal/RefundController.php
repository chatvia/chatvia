<?php

namespace App\Http\Controllers\Landing\Legal;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class RefundController
{
    public function __invoke(Request $request): Factory|View
    {
        return view('pages.refund');
    }
}
