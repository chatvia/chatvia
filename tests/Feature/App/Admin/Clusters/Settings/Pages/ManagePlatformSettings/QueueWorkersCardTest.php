<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\RestartMasterSupervisor;
use App\Identity\Workspace\Models\User;
use Domain\Conversation\Services\Voice\VoiceCallSlots;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Tests\Support\Admin\FakeHorizonWorkers;

/**
 * Settings → Platform → Queue workers. Horizon reads the worker numbers only
 * when it starts, so the card says "Restart needed" while the running Horizon
 * uses other numbers than the saved ones, and its "Restart queue" button asks
 * every running Horizon to restart. It also says when the queue is not
 * running or is restarting. The numbers apply in every environment, and the
 * button is never greyed out: when there is nothing to restart, the
 * notification says why.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');

    saveSettings(PlatformSettings::class, ['chat_workers' => 8]);

    // A Horizon that still runs 6 chat workers.
    $this->horizon = FakeHorizonWorkers::bind();
});

function qwcPage(User $admin, string $environment = 'production'): Testable
{
    app()['env'] = $environment;

    return adminPage(ManagePlatformSettings::class, $admin);
}

function qwcRestartButton(): TestAction
{
    return TestAction::make('restart_queue')->schemaComponent('restart-queue', schema: 'form');
}

it('says a restart is needed while Horizon runs other numbers than the saved ones', function (): void {
    qwcPage($this->admin)->assertSee('Restart needed');
});

it('says nothing once Horizon runs the saved numbers', function (): void {
    $this->horizon->supervisors = ['web-1-abcd:chat' => 8];

    qwcPage($this->admin)->assertDontSee('Restart needed');
});

it('restarts every running Horizon after a confirm, in production and outside it', function (string $environment): void {
    qwcPage($this->admin, $environment)
        ->assertSee('Restart needed')
        ->assertDontSee('Production only')
        ->assertActionVisible(qwcRestartButton())
        ->callAction(qwcRestartButton())
        ->assertNotified('The queue is restarting');

    expect($this->horizon->commands)->toBe([['master:web-1-abcd', RestartMasterSupervisor::class]]);
})->with(['production', 'local']);

it('says the queue is not running, and the button says so too', function (): void {
    $this->horizon->masters = [];
    $this->horizon->supervisors = [];

    qwcPage($this->admin)
        ->assertSee('Queue not running')
        ->assertDontSee('Restart needed')
        ->assertActionEnabled(qwcRestartButton())
        ->callAction(qwcRestartButton())
        ->assertNotified('The queue is not running');

    expect($this->horizon->commands)->toBe([]);
});

it('says the queue is restarting while its supervisors finish their jobs', function (): void {
    $this->horizon->masters = [];

    qwcPage($this->admin)
        ->assertSee('Queue restarting')
        ->assertActionEnabled(qwcRestartButton())
        ->callAction(qwcRestartButton())
        ->assertNotified('The queue is already restarting');

    expect($this->horizon->commands)->toBe([]);
});

it('sets how many voice calls run at once', function (): void {
    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['voice_workers' => 6])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('horizon.defaults.voice.maxProcesses'))->toBe(6)
        ->and(app(VoiceCallSlots::class)->capacity())->toBe(6);
});
