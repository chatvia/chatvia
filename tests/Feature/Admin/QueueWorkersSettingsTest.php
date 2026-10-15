<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\HorizonWorkers;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\RestartMasterSupervisor;
use App\Identity\Workspace\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

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
    $this->admin = User::factory()->admin()->create()->fresh();
    Filament::setCurrentPanel('admin');

    $settings = app(PlatformSettings::class);
    $settings->chat_workers = 8;
    $settings->save();

    $this->masters = Mockery::mock(MasterSupervisorRepository::class);
    $this->masters->allows('names')->andReturn(['web-1-abcd'])->byDefault();
    $this->supervisors = Mockery::mock(SupervisorRepository::class);
    $this->supervisors->allows('all')->andReturn([(object) ['name' => 'web-1-abcd:chat', 'options' => ['maxProcesses' => 6]]])->byDefault();
    $this->supervisors->allows('names')->andReturn(['web-1-abcd:chat'])->byDefault();
    $this->commands = Mockery::mock(HorizonCommandQueue::class);

    app()->instance(HorizonWorkers::class, new HorizonWorkers($this->masters, $this->supervisors, $this->commands));
});

function queueWorkersPage(User $admin, string $environment = 'production'): Testable
{
    app()['env'] = $environment;

    return Livewire::actingAs($admin, 'admin')->test(ManagePlatformSettings::class);
}

function restartQueueButton(): TestAction
{
    return TestAction::make('restart_queue')->schemaComponent('restart-queue', schema: 'form');
}

it('says a restart is needed while Horizon runs other numbers than the saved ones', function (): void {
    queueWorkersPage($this->admin)->assertSee('Restart needed');
});

it('says nothing once Horizon runs the saved numbers', function (): void {
    $this->supervisors->allows('all')->andReturn([(object) ['name' => 'web-1-abcd:chat', 'options' => ['maxProcesses' => 8]]]);

    queueWorkersPage($this->admin)->assertDontSee('Restart needed');
});

it('restarts every running Horizon after a confirm', function (): void {
    $this->commands->expects('push')->with('master:web-1-abcd', RestartMasterSupervisor::class);

    queueWorkersPage($this->admin)
        ->assertActionVisible(restartQueueButton())
        ->callAction(restartQueueButton())
        ->assertNotified('The queue is restarting');
});

it('says the queue is not running, and the button says so too', function (): void {
    $this->masters->allows('names')->andReturn([]);
    $this->supervisors->allows('names')->andReturn([]);
    $this->commands->shouldNotReceive('push');

    queueWorkersPage($this->admin)
        ->assertSee('Queue not running')
        ->assertDontSee('Restart needed')
        ->assertActionEnabled(restartQueueButton())
        ->callAction(restartQueueButton())
        ->assertNotified('The queue is not running');
});

it('says the queue is restarting while its supervisors finish their jobs', function (): void {
    $this->masters->allows('names')->andReturn([]);
    $this->commands->shouldNotReceive('push');

    queueWorkersPage($this->admin)
        ->assertSee('Queue restarting')
        ->assertActionEnabled(restartQueueButton())
        ->callAction(restartQueueButton())
        ->assertNotified('The queue is already restarting');
});

it('restarts the queue outside production too', function (): void {
    $this->commands->expects('push')->with('master:web-1-abcd', RestartMasterSupervisor::class);

    queueWorkersPage($this->admin, 'local')
        ->assertSee('Restart needed')
        ->assertDontSee('Production only')
        ->callAction(restartQueueButton())
        ->assertNotified('The queue is restarting');
});
