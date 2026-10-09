<?php

namespace App\Http\Controllers\Landing\Blog;

use App\Marketing\Models\Post;
use Illuminate\Contracts\View\View;

/**
 * The public blog index: the featured post on top, then every other post,
 * newest first.
 */
class BlogController
{
    public function __invoke(): View
    {
        return view('landing.blog', [
            'featured' => Post::featured()->first(),
            'posts' => Post::query()->where('is_featured', false)->latest()->get(),
        ]);
    }
}
