<?php

namespace App\Http\Controllers\Landing\Blog;

use App\Marketing\Models\Post;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PostController
{
    public function __invoke(Request $request, Post $post): Factory|View
    {
        return view('landing.post', [
            'post' => $post,
        ]);
    }
}
