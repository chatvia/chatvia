<?php

use App\Admin\Pages\Tools;
use App\Admin\StaffTool;
use App\Identity\Workspace\Models\User;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Horizon\Horizon;

/*
 * The admin panel's Tools page: a card per staff tool (Horizon, Pulse,
 * Spectra) with what it is for and a link that opens it. It sits above
 * Settings, below the sidebar's divider, and opens for the same staff as the
 * tools. A tool this install lacks, or that the admin may not open, has no
 * link.
 */

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
});

it('shows a card with a link for each staff tool', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(Tools::getUrl())
        ->assertOk()
        ->assertSee('Tools')
        ->assertSeeInOrder(['Laravel Horizon', 'Queues and workers', 'Open Laravel Horizon'])
        ->assertSeeInOrder(['Laravel Pulse', 'App health and performance', 'Open Laravel Pulse'])
        ->assertSeeInOrder(['Spectra', 'AI calls and their cost', 'Open Spectra'])
        ->assertSee('href="'.route('horizon.index').'"', false)
        ->assertSee('href="'.route('pulse').'"', false)
        ->assertSee('href="'.route('spectra.dashboard').'"', false)
        ->assertSee('src="'.asset('images/logos/tools/horizon.svg').'"', false)
        ->assertSee('src="'.asset('images/logos/tools/pulse.svg').'"', false)
        ->assertSee('src="'.asset('images/logos/tools/spectra.svg').'"', false)
        ->assertDontSee('Not installed');
});

it('shows a tool the admin may not open without its link', function (): void {
    Horizon::auth(fn (): bool => false);

    $this->actingAs($this->admin, 'admin')
        ->get(Tools::getUrl())
        ->assertOk()
        ->assertSee('You cannot open it')
        ->assertDontSee('Open Laravel Horizon')
        ->assertSee('Open Laravel Pulse');
});

it('says a tool without its dashboard route is not installed', function (): void {
    Auth::guard('admin')->login($this->admin);

    expect(StaffTool::Pulse->unavailableReason())->toBeNull();

    Route::setRoutes(new RouteCollection);

    expect(StaffTool::Pulse->unavailableReason())->toBe('Not installed')
        ->and(StaffTool::Horizon->unavailableReason())->toBe('Not installed');
});
