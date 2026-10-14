<?php

use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Saade\FilamentLaravelLog\FilamentLaravelLogPlugin;
use Saade\FilamentLaravelLog\Pages\ViewLog;

/**
 * The admin panel's Logs page reads and clears the install's log files. It
 * opens only for staff signed in to the panel, and clears only the files it
 * lists, never a path typed into the request.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();

    $this->logDir = storage_path('framework/testing/logs-'.uniqid());
    File::ensureDirectoryExists($this->logDir);

    FilamentLaravelLogPlugin::get(Filament::getPanel('admin'))->logDirs([$this->logDir]);
});

afterEach(function (): void {
    File::deleteDirectory($this->logDir);
});

it('opens the logs page for an admin signed in to the panel', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ViewLog::getUrl())
        ->assertOk()
        ->assertSee('Logs');
});

it('keeps a dashboard (web guard) session out of the logs page', function (): void {
    $this->actingAs($this->admin, 'web')
        ->get(ViewLog::getUrl())
        ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
});

it('clears the selected log file', function (): void {
    $file = $this->logDir.'/laravel.log';
    File::put($file, "[2026-10-14 10:00:00] local.ERROR: Something broke\n");

    Livewire::actingAs($this->admin, 'admin')
        ->test(ViewLog::class)
        ->set('logFile', $file)
        ->callAction('clear')
        ->assertHasNoErrors();

    expect(File::get($file))->toBe('');
});

it('does not clear a file outside the log folders', function (): void {
    $outside = $this->logDir.'-outside.log';
    File::put($outside, 'keep me');

    try {
        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewLog::class)
            ->set('logFile', $outside)
            ->callAction('clear');

        expect(File::get($outside))->toBe('keep me');
    } finally {
        File::delete($outside);
    }
});
