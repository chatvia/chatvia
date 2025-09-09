<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PostController
{
    public function __invoke(Request $request)
    {
        return view('landing.post');
    }
}
