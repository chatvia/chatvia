<?php

namespace Database\Factories;

use App\Marketing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds a published marketing blog post with the same short text as excerpt,
 * markdown and HTML.
 *
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    #[\Override]
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence();
        $body = fake()->paragraph();

        return [
            'title' => $title,
            'slug' => Str::kebab($title),
            'excerpt' => $body,
            'markdown' => $body,
            'html' => $body,
            'status' => 'published',
            'published_at' => now(),
        ];
    }
}
