<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DocsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Factory|View
    {
        return view('docs.docs');
    }
}
