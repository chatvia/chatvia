<?php

use App\Marketing\Models\Post;

it('renders a post without a cover image and closes every output buffer', function (): void {
    $post = Post::factory()->create(['image_path' => null]);
    $level = ob_get_level();

    $this->get(route('blog.post', $post))
        ->assertOk()
        ->assertSee($post->title)
        ->assertSee('<meta property="og:image" content="'.asset('images/chatvia.png').'" />', false);

    expect(ob_get_level())->toBe($level);
});

it('uses the post cover image for the social previews', function (): void {
    $post = Post::factory()->create(['image_path' => 'blog/cover.png']);

    $this->get(route('blog.post', $post))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.e($post->img_url).'" />', false)
        ->assertSee('<meta name="twitter:image" content="'.e($post->img_url).'" />', false);
});
