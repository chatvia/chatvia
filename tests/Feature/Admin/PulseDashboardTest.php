<?php

use App\Identity\Workspace\Models\User;
use Laravel\Pulse\Contracts\ResolvesUsers;

/*
 * Pulse (/pulse) opens for staff signed in to the admin panel only, like
 * Horizon (see StaffToolsAccessTest), and shows people without loading
 * Gravatar pictures.
 */

it('opens the dashboard for an admin signed in to the admin panel', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'admin')
        ->get('/pulse')
        ->assertOk()
        ->assertSee('Pulse');
});

it('refuses the dashboard on a dashboard session', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web')
        ->get('/pulse')
        ->assertForbidden();
});

it('shows a person by name and email, without a Gravatar picture', function (): void {
    $user = User::factory()->workspace()->create(['profile_image_path' => null]);

    $shown = app(ResolvesUsers::class)->load(collect([$user->getKey()]))->find($user->getKey());

    expect($shown->name)->toBe($user->name)
        ->and($shown->extra)->toBe($user->email)
        ->and(property_exists($shown, 'avatar'))->toBeFalse();
});
