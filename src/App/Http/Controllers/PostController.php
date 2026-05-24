<?php

namespace App\Http\Controllers;

use App\Marketing\Models\Post;
use Illuminate\Http\Request;

class PostController
{
    public function __invoke(Request $request, Post $post)
    {
        return view('landing.post', [
            'post' => $post,
        ]);
    }
}
