<?php

use App\Identity\Workspace\Enums\DateFormat;
use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('renders the date & time form on the profile page', function (): void {
    $this->actingAs($this->user)
        ->get(route('account.profile.edit'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('account/Profile')
                ->has('dateFormats', count(DateFormat::cases())),
        );
});

it('updates the date format preference', function (): void {
    $this->actingAs($this->user)
        ->patch(route('account.preferences.update'), [
            'date_format' => DateFormat::DmySlash->value,
        ])
        ->assertRedirect();

    expect($this->user->fresh()->date_format)->toBe(DateFormat::DmySlash);
});

it('rejects an unknown date format', function (): void {
    $this->actingAs($this->user)
        ->patch(route('account.preferences.update'), [
            'date_format' => 'not-a-format',
        ])
        ->assertSessionHasErrors('date_format');
});

it('requires a date format', function (): void {
    $this->actingAs($this->user)
        ->patch(route('account.preferences.update'), [])
        ->assertSessionHasErrors('date_format');
});

it('updates the locale preference', function (): void {
    $this->actingAs($this->user)
        ->patch(route('account.preferences.update'), [
            'date_format' => DateFormat::DmySlash->value,
            'locale' => 'ar',
        ])
        ->assertRedirect();

    expect($this->user->fresh()->locale)->toBe('ar');
});

it('normalises an "auto" locale to null', function (): void {
    $this->user->update(['locale' => 'ar']);

    $this->actingAs($this->user)
        ->patch(route('account.preferences.update'), [
            'date_format' => DateFormat::DmySlash->value,
            'locale' => 'auto',
        ])
        ->assertRedirect();

    expect($this->user->fresh()->locale)->toBeNull();
});

it('rejects an unsupported locale', function (): void {
    $this->actingAs($this->user)
        ->patch(route('account.preferences.update'), [
            'date_format' => DateFormat::DmySlash->value,
            'locale' => 'not-a-locale',
        ])
        ->assertSessionHasErrors('locale');
});

it('leaves the locale untouched when the form omits it', function (): void {
    $this->user->update(['locale' => 'ar']);

    $this->actingAs($this->user)
        ->patch(route('account.preferences.update'), [
            'date_format' => DateFormat::DmySlash->value,
        ])
        ->assertRedirect();

    expect($this->user->fresh()->locale)->toBe('ar');
});
