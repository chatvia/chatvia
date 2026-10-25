<?php

use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * An agent's custom domain: a new one is saved trimmed, in lower case and
 * pending until its TXT record is verified; an empty value clears it. The
 * app's own host, any host under it, another agent's domain and anything
 * that is not a bare host name are refused, and a domain in use answers with
 * the same generic words as any unusable one.
 */
beforeEach(function (): void {
    config(['app.url' => 'https://app.chatvia.test']);
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['domain' => 'chat.acme-bot.test']);
});

it('saves a new custom domain trimmed, in lower case and pending', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('agents.domain.update', $this->agent), ['domain' => ' Help.Acme-Bot.TEST '])
        ->assertRedirect();

    expect($this->agent->fresh())
        ->pending_domain->toBe('help.acme-bot.test')
        ->domain->toBe('chat.acme-bot.test');
});

it('shows the TXT record that proves the pending domain on the Setup page', function (): void {
    $this->agent->update(['pending_domain' => 'help.acme-bot.test', 'domain_verification_token' => 'chatvia-verify-abc']);

    $this->actingAs($this->owner)
        ->get(route('agents.setup', $this->agent->external_id))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('domain.value', 'chat.acme-bot.test')
            ->where('domain.pending', 'help.acme-bot.test')
            ->where('domain.txt_name', '_chatvia.help.acme-bot.test')
            ->where('domain.txt_value', 'chatvia-verify-abc'));
});

it('clears the custom domain when it is sent empty', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('agents.domain.update', $this->agent), ['domain' => ''])
        ->assertRedirect();

    expect($this->agent->fresh())->domain->toBeNull()->pending_domain->toBeNull();
});

it('refuses a domain the agent cannot have', function (string $domain): void {
    createAgent(User::factory()->create(), ['domain' => 'taken.acme-bot.test']);

    $this->actingAs($this->owner)
        ->patchJson(route('agents.domain.update', $this->agent), ['domain' => $domain])
        ->assertJsonValidationErrors(['domain']);

    expect($this->agent->fresh())->domain->toBe('chat.acme-bot.test')->pending_domain->toBeNull();
})->with([
    'the app\'s own host' => 'app.chatvia.test',
    'the app\'s own host in capitals' => 'App.Chatvia.TEST',
    'a host under the app\'s own host' => 'evil.app.chatvia.test',
    'another agent\'s domain' => 'taken.acme-bot.test',
    'a URL, not a host' => 'https://chat.acme-bot.test/path',
]);

it('does not tell that another workspace uses the domain', function (): void {
    createAgent(User::factory()->create(), ['domain' => 'taken.acme-bot.test']);

    $this->actingAs($this->owner)
        ->patchJson(route('agents.domain.update', $this->agent), ['domain' => 'taken.acme-bot.test'])
        ->assertJsonValidationErrors(['domain' => "This domain can't be used. Choose another domain."]);
});
