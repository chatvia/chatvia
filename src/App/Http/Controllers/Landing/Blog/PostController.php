<?php

namespace App\Http\Controllers\Landing\Blog;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Support\Cms\Marketing\Models\Post;

class PostController
{
    public function __invoke(Request $request, Post $post): Factory|View
    {
        return view('landing.post', [
            'post' => $post,
        ]);
    }
}
