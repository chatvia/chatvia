<?php

use App\Identity\Workspace\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

/*
 * Spectra (/spectra), the AI call log, opens for staff signed in to the admin
 * panel only, like Horizon and Pulse (see StaffToolsAccessTest). The Guzzle
 * client that sends the streamed replies goes through Spectra, so a streamed
 * reply is logged like any other call.
 */

it('opens the dashboard for an admin signed in to the admin panel', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'admin')
        ->get('/spectra')
        ->assertOk()
        ->assertSee('Spectra');
});

it('refuses the dashboard on a dashboard session', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web')
        ->get('/spectra')
        ->assertForbidden();
});

it('refuses the dashboard to a guest', function (): void {
    $this->get('/spectra')->assertForbidden();
});

it('sends the streamed replies through Spectra', function (): void {
    $handler = app(Client::class)->getConfig('handler');

    expect($handler)->toBeInstanceOf(HandlerStack::class)
        ->and((string) $handler)->toContain('spectra');
});
