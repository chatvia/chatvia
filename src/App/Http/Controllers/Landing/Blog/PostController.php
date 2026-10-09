<?php

namespace App\Http\Controllers\Landing\Blog;

use App\Marketing\Models\Post;
use Illuminate\Contracts\View\View;

/**
 * One public blog post, bound by its slug.
 */
class PostController
{
    public function __invoke(Post $post): View
    {
        return view('landing.post', ['post' => $post]);
    }
}
