<?php

use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Both Inertia middlewares (dashboard + hosted chat) share config/chatvia.php
 * `brand`, so a self-hosted install's name and links reach the Vue pages.
 * URLs pass through as set; unset ones stay null so the page hides the link.
 */
beforeEach(function (): void {
    config([
        'chatvia.brand.name' => 'Acme Chat',
        'chatvia.brand.docs_url' => 'https://docs.example.com',
        'chatvia.brand.api_docs_url' => null,
        'chatvia.brand.powered_by' => ['enabled' => true, 'label' => 'Acme', 'url' => null],
        'chatvia.brand.onboarding_video_url' => null,
    ]);
});

it('shares the brand block with dashboard pages', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('brand.name', 'Acme Chat')
            ->where('brand.docs_url', 'https://docs.example.com')
            ->where('brand.api_docs_url', null)
            ->where('brand.powered_by.label', 'Acme')
            ->where('brand.powered_by.url', null)
            ->where('brand.onboarding_video_url', null));
});

it('shares the brand block with the hosted chat', function (): void {
    $agent = proPlanAgent();
    $agent->update(['is_public' => true]);
    fakeMessageCreditGate();

    $this->get('https://'.$agent->sub_domain.'.chatvia.test/chat')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('brand.name', 'Acme Chat')
            ->where('brand.powered_by.enabled', true)
            ->where('brand.powered_by.label', 'Acme')
            ->where('brand.powered_by.url', null));
});
