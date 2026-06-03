<?php

namespace App\Http\Controllers\Landing\Blog;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Support\Cms\Marketing\Models\Post;

class BlogController
{
    public function __invoke(Request $request): Factory|View
    {
        return view('landing.blog', [
            'featured' => Post::featured()->first(),
            'posts' => Post::query()->where('is_featured', false)->latest()->get(),
        ]);
    }
}
