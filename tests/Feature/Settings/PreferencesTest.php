<?php

use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;
use Support\Enums\User\DateFormat;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('renders the date & time form on the profile page', function (): void {
    $this->actingAs($this->user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('settings/Profile')
                ->has('dateFormats', count(DateFormat::cases())),
        );
});

it('updates the date format preference', function (): void {
    $this->actingAs($this->user)
        ->patch(route('settings.preferences.update'), [
            'date_format' => DateFormat::DmySlash->value,
        ])
        ->assertRedirect();

    expect($this->user->fresh()->date_format)->toBe(DateFormat::DmySlash);
});

it('rejects an unknown date format', function (): void {
    $this->actingAs($this->user)
        ->patch(route('settings.preferences.update'), [
            'date_format' => 'not-a-format',
        ])
        ->assertSessionHasErrors('date_format');
});

it('requires a date format', function (): void {
    $this->actingAs($this->user)
        ->patch(route('settings.preferences.update'), [])
        ->assertSessionHasErrors('date_format');
});
