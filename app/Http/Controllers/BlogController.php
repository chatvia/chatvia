<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BlogController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return view('landing.blog');
    }
}
