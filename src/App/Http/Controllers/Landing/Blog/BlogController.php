<?php

namespace App\Http\Controllers\Landing\Blog;

use App\Marketing\Models\Post;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BlogController
{
    public function __invoke(Request $request): Factory|View
    {
        return view('landing.blog', [
            'featured' => Post::featured()->first(),
            'posts' => Post::query()->where('featured', false)->latest()->get(),
        ]);
    }
}
