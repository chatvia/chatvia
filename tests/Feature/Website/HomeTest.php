<?php

namespace Tests\Feature\Website;

use PHPUnit\Framework\Attributes\Test;
use Support\Cms\Marketing\Models\Post;
use Tests\TestCase;

class HomeTest extends TestCase
{
    #[Test]
    public function home_page_should_work(): void
    {
        $this->get(route('home'))->assertStatus(200);
    }

    #[Test]
    public function blog_should_work(): void
    {
        Post::factory()->create();

        $this->get(route('blog'))->assertStatus(200);
    }

    #[Test]
    public function changelog_page_should_work(): void
    {
        $this->get(route('changelog'))->assertStatus(200);
    }

    #[Test]
    public function tos_page_should_work(): void
    {
        $this->get(route('terms'))->assertStatus(200);
    }

    #[Test]
    public function privacy_page_should_work(): void
    {
        $this->get(route('privacy'))->assertStatus(200);
    }

    #[Test]
    public function refund_page_should_work(): void
    {
        $this->get(route('refund'))->assertStatus(200);
    }
}
