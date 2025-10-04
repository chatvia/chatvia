<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class BlogController
{
    public function __invoke(Request $request)
    {
        return view('landing.blog', [
            'featured' => Post::featured()->first(),
            'posts' => Post::query()->where('featured', false)->latest()->get(),
        ]);
    }
}
