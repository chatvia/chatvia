<?php

use App\Identity\Workspace\Models\User;

/**
 * The `license.analytics` route middleware: without a license key the
 * sentiment, tone, topics and sources data is refused, while their pages
 * still open (they show the lock) and the Chats analytics stay free. Any
 * paid key (Pro or Enterprise) opens the data again.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

dataset('locked analytics data', [
    'sentiment' => ['agents.sentiment.data'],
    'tone' => ['agents.tone.data'],
    'topics' => ['agents.topics.data'],
    'topic suggestions' => ['agents.topics.suggestions'],
    'sources' => ['agents.analytics.sources.data'],
]);

it('refuses the data without a license key', function (string $route): void {
    $this->actingAs($this->owner)
        ->getJson(route($route, $this->agent))
        ->assertForbidden()
        ->assertJsonPath('message', 'This feature is unavailable. Please contact your administrator.');
})->with('locked analytics data');

it('refuses promoting a topic suggestion without a license key, and still opens the locked pages and the free Chats analytics', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.topics.suggestions.promote', $this->agent), ['label' => 'refunds'])
        ->assertForbidden();

    $this->get(route('agents.analytics.sentiment', $this->agent))->assertOk();
    $this->get(route('agents.analytics.sources', $this->agent))->assertOk();
    $this->get(route('agents.analytics.data', $this->agent))->assertOk();

    expect($this->agent->topics()->exists())->toBeFalse();
});

it('opens the data with a paid license key', function (Closure $license): void {
    $license();
    $this->actingAs($this->owner);

    foreach (['agents.sentiment.data', 'agents.tone.data', 'agents.topics.data', 'agents.topics.suggestions', 'agents.analytics.sources.data'] as $route) {
        $this->getJson(route($route, $this->agent))->assertOk();
    }
})->with([
    'Pro' => [fn () => withLicense()],
    'Enterprise' => [fn () => withEnterpriseLicense()],
]);
