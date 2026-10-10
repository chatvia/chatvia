<?php

use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The hosted chat exposes a visitor language switcher (header), controlled by
 * the embed `show_language_switcher` setting. Default on; the owner can hide it.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->agent->update(['is_public' => true]);
    $this->host = $this->agent->sub_domain.'.chatvia.test';
});

it('defaults the embed language-switcher setting on', function (): void {
    expect($this->agent->settings('embed')->get('show_language_switcher'))->toBeTrue();
});

it('passes the language switcher flag to the hosted chat home (default on)', function (): void {
    $this->get('https://'.$this->host.'/chat')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('enableLanguageSwitcher', true));
});

it('hides the switcher when the owner turns it off', function (): void {
    $this->agent->setSetting('show_language_switcher', false, 'embed');

    $this->get('https://'.$this->host.'/chat')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('enableLanguageSwitcher', false));
});

it('persists the owner toggle through the settings endpoint', function (): void {
    $this->actingAs($this->agent->user)
        ->post(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'embed']), [
            'show_language_switcher' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($this->agent->fresh()->settings('embed')->get('show_language_switcher'))->toBeFalse();
});

it('passes the full language list to the hosted chat when none are curated', function (): void {
    $all = array_keys($this->agent->enabledLanguages());

    $this->get('https://'.$this->host.'/chat')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('enabledLanguages', $all));
});

it('restricts the hosted-chat switcher list to the owner-selected languages', function (): void {
    $this->agent->setSetting('enabled_languages', ['de', 'fr'], 'appearance');

    $this->get('https://'.$this->host.'/chat')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('chat/Home')
            ->where('enabledLanguages', ['de', 'fr']));
});
