<?php

namespace Database\Factories;

use App\Marketing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = $this->faker->sentence();
        $body = $this->faker->paragraph();

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
