<?php

namespace App\Http\Controllers\Landing\Marketing;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DocController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Factory|View
    {
        return view('docs.docs');
    }
}
