<?php

use App\Admin\Settings\PlatformSettings;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentDomain;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An agent's custom domain serves that agent's Hosted Chat and nothing else
 * (ServeCustomDomain): `/` leads to the chat, the chat's own pages work and
 * link within the domain, and the dashboard, the admin panel and other agents
 * are not there. It only works with a license key and the admin's switch.
 */
beforeEach(function (): void {
    withLicense();
    saveSettings(PlatformSettings::class, ['custom_domains_enabled' => true]);

    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['is_public' => true, 'slug' => Str::lower(Str::random(12))]);
    $this->domain = AgentDomain::factory()->forAgent($this->agent)->verified()->create(['host' => 'help.acme.test']);
});

it('sends a visitor from the domain\'s root to the chat, keeping the query', function (): void {
    $this->get('http://help.acme.test/?auth_token=abc')
        ->assertRedirect("http://help.acme.test/chat/{$this->agent->slug}?auth_token=abc");
});

it('serves the agent\'s own chat on the domain', function (): void {
    $this->get("http://help.acme.test/chat/{$this->agent->slug}")
        ->assertInertia(fn (Assert $page): Assert => $page->component('chat/pages/Home'));

    $this->get("http://help.acme.test/chat/{$this->agent->external_id}")
        ->assertOk();
});

it('builds the chat\'s links and redirects on the domain', function (): void {
    $this->agent->update(['is_public' => false]);

    $this->get("http://help.acme.test/chat/{$this->agent->slug}")
        ->assertRedirect("http://help.acme.test/chat/{$this->agent->slug}/login");
});

it('serves nothing else on the domain', function (string $path): void {
    $other = createAgent($this->owner, ['is_public' => true]);

    $this->get('http://help.acme.test'.Str::replace('{other}', $other->slug ?? $other->external_id, $path))
        ->assertNotFound();
})->with([
    'another agent' => ['/chat/{other}'],
    'the dashboard' => ['/dashboard'],
    'the sign-in page' => ['/login'],
    'the admin panel' => ['/admin'],
]);

it('stops leading the domain to the chat without a license key, and links on APP_URL again', function (): void {
    withoutLicense();

    $this->get('http://help.acme.test/')
        ->assertRedirect(config('app.url').'/login');

    expect($this->agent->fresh()->url)->toBe(config('app.url')."/chat/{$this->agent->slug}");
});

it('answers the check of a waiting domain with its token only', function (): void {
    $waiting = AgentDomain::factory()->create(['host' => 'chat.other.test']);

    $this->get("http://chat.other.test/.well-known/chatvia-domain/{$waiting->verification_token}")
        ->assertOk()
        ->assertSeeText($waiting->verification_token);

    $this->get('http://chat.other.test/.well-known/chatvia-domain/wrong-token')
        ->assertNotFound();

    $this->get('http://chat.other.test/')
        ->assertNotFound();
});

it('shares the agent at its domain once verified', function (): void {
    expect($this->agent->fresh())
        ->url->toBe('https://help.acme.test')
        ->chat_url->toBe("https://help.acme.test/chat/{$this->agent->slug}")
        ->chat_embed_url->toBe("https://help.acme.test/chat/{$this->agent->external_id}/embed");

    $this->domain->update(['verified_at' => null]);

    expect($this->agent->fresh()->url)->toBe(config('app.url')."/chat/{$this->agent->slug}");
});

it('sends a staff member from the dashboard hand-off to the chat on its domain', function (): void {
    $location = $this->actingAs($this->owner)
        ->get("/chat-handoff/{$this->agent->slug}")
        ->assertRedirect()
        ->headers->get('Location');

    expect($location)->toStartWith("https://help.acme.test/chat/{$this->agent->slug}?auth_token=");
});
