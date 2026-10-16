<?php

use App\Admin\Clusters\Settings\SettingsCluster;
use App\Admin\Pages\Dashboard;
use App\Admin\Pages\Status;
use App\Admin\Pages\Tools;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Status\AiService;
use App\Admin\Status\AiServiceChecks;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;
use App\Admin\Widgets\ServiceStatusBanner;
use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * The admin panel's Status page: whether the services the install needs are
 * up (database, Redis, queue, scheduler, Reverb, Pulse, storage, email,
 * programs), whether the AI and other outside services accept their keys,
 * and the environment as `php artisan about` tells it. It sits below Tools
 * and opens for the same staff.
 */

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    Filament::setCurrentPanel('admin');
    Storage::fake('private');
    Storage::fake('public');
});

it('opens with every service waiting for its check, and the environment', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(Status::getUrl())
        ->assertOk()
        ->assertSee('Checking…')
        ->assertSeeInOrder(array_map(fn (Service $service): string => $service->label(), Service::cases()))
        ->assertSeeInOrder(array_map(fn (AiService $service): string => $service->label(), AiService::cases()))
        ->assertSeeInOrder(['Environment', 'PHP', PHP_VERSION, 'Laravel', app()->version()]);
});

it('lists the page below Tools and above Settings', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['cv-sidebar-group--divided', 'href="'.Tools::getUrl().'"', 'href="'.Status::getUrl().'"', 'href="'.SettingsCluster::getUrl().'"'], false);
});

it('checks every service, then the outside services', function (): void {
    Http::fake();

    $page = Livewire::actingAs($this->admin, 'admin')->test(Status::class)
        ->call('checkServices')
        ->assertSee('Checked at')
        ->assertSee('Still checking the AI services…');

    expect(array_keys($page->get('services')))->toBe(array_map(fn (Service $service): string => $service->value, Service::cases()))
        ->and($page->get('aiServices'))->toBe([]);

    $page->call('checkAiServices')->assertDontSee('Still checking the AI services…');

    expect(array_keys($page->get('aiServices')))->toBe(array_map(fn (AiService $service): string => $service->value, AiService::cases()));
});

it('counts the services that are down in the banner', function (): void {
    $down = ['state' => 'down', 'summary' => 'Not running.', 'facts' => [], 'providers' => []];
    $warning = ['state' => 'warning', 'summary' => 'Paused.', 'facts' => [], 'providers' => []];
    $running = ['state' => 'running', 'summary' => 'Answers.', 'facts' => [], 'providers' => []];

    Livewire::actingAs($this->admin, 'admin')->test(Status::class)
        ->set('checkedAt', now()->toIso8601String())
        ->set('services', ['database' => $running, 'queue' => $down, 'scheduler' => $down])
        ->set('aiServices', ['chat' => $warning])
        ->assertSee('2 services are down')
        ->set('services', ['database' => $running])
        ->assertSee('1 service needs a look')
        ->set('aiServices', ['chat' => $running])
        ->assertSee('Everything is running');
});

it('calls a service running and an outside service set up', function (): void {
    $running = ['state' => 'running', 'summary' => 'Answers.', 'facts' => [], 'providers' => []];

    Livewire::actingAs($this->admin, 'admin')->test(Status::class)
        ->set('checkedAt', now()->toIso8601String())
        ->set('services', ['database' => $running])
        ->set('aiServices', ['chat' => $running])
        ->assertSeeInOrder(['Database', 'Running', 'Chat models', 'Set up']);
});

it('finds the database with the extensions the search needs', function (): void {
    $status = app(ServiceChecks::class)->check(Service::Database);

    expect($status->state)->toBe(ServiceState::Running)
        ->and($status->facts[0])->toStartWith('PostgreSQL ');
});

it('saves a file to each disk and reads it back', function (): void {
    expect(app(ServiceChecks::class)->check(Service::Storage)->state)->toBe(ServiceState::Running)
        ->and(Storage::disk('private')->allFiles())->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('says when live updates do not go through Reverb, or Reverb does not answer', function (): void {
    config(['broadcasting.default' => 'log']);
    expect(app(ServiceChecks::class)->check(Service::Realtime)->state)->toBe(ServiceState::Off);

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.options' => ['host' => '127.0.0.1', 'port' => 1, 'scheme' => 'http', 'useTLS' => false],
    ]);
    Http::fake(['127.0.0.1:1/*' => Http::failedConnection()]);
    $status = app(ServiceChecks::class)->check(Service::Realtime);

    expect($status->state)->toBe(ServiceState::Down)
        ->and($status->summary)->toContain('does not answer at http://127.0.0.1:1');
});

it('finds Reverb running with no open channels, and says when it refuses the keys', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.app_id' => 'test-app-id',
        'broadcasting.connections.reverb.options' => ['host' => 'reverb.test', 'port' => 443, 'scheme' => 'https', 'useTLS' => true],
    ]);

    // Reverb lists no channels as [], not as an object.
    Http::fakeSequence('reverb.test/*')
        ->push(['channels' => []])
        ->push('Invalid auth signature provided.', 401);

    $running = app(ServiceChecks::class)->check(Service::Realtime);
    expect($running->state)->toBe(ServiceState::Running)
        ->and($running->facts)->toContain('0 open channels');

    $refused = app(ServiceChecks::class)->check(Service::Realtime);
    expect($refused->state)->toBe(ServiceState::Down)
        ->and($refused->summary)->toContain('refused the request (401)');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'reverb.test')
        && str_contains($request->url(), '/apps/test-app-id/channels?')
        && str_contains($request->url(), 'auth_signature='));
});

it('warns when email goes to the log, and finds an SMTP server that does not answer', function (): void {
    config(['mail.default' => 'log']);
    expect(app(ServiceChecks::class)->check(Service::Email)->state)->toBe(ServiceState::Warning);

    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
    expect(app(ServiceChecks::class)->check(Service::Email)->state)->toBe(ServiceState::Down);
});

it('names the programs that are not found', function (): void {
    config(['services.bin.catdoc' => '/no/such/folder/catdoc']);

    $status = app(ServiceChecks::class)->check(Service::Programs);

    expect($status->state)->toBe(ServiceState::Warning)
        ->and($status->summary)->toContain('catdoc');
});

it('is off while Pulse is turned off', function (): void {
    config(['pulse.enabled' => false]);

    expect(app(ServiceChecks::class)->check(Service::ServerMonitor)->state)->toBe(ServiceState::Off);
});

it('checks Firecrawl only once it has a key', function (): void {
    config(['services.firecrawl.key' => null]);
    expect(app(AiServiceChecks::class)->check(AiService::WebPages)->state)->toBe(ServiceState::Off);

    Http::fake(['*' => Http::response(['error' => 'Unauthorized'], 401)]);
    config(['services.firecrawl.key' => 'fc-refused']);

    expect(app(AiServiceChecks::class)->check(AiService::WebPages)->state)->toBe(ServiceState::Down);
});

it('reloads the saved settings from the Caches card', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(Status::getUrl())
        ->assertSee('Reload the saved settings and AI models');

    DB::table('settings')
        ->where('group', AiProviderSettings::group())
        ->where('name', 'openrouter_api_key')
        ->update(['payload' => json_encode(encrypt('sk-or-reloaded'))]);

    Livewire::actingAs($this->admin, 'admin')->test(Status::class)
        ->call('reloadSettings')
        ->assertNotified('Settings reloaded');

    expect(config('services.openrouter.api_key'))->toBe('sk-or-reloaded');
});

/*
 * The dashboard's status banner: the same checks, said in one line, with
 * the services that are not fine listed and a link to this page.
 */

it('puts a status banner on the dashboard that checks once it is open', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('Asking each service how it is.')
        ->assertSee('href="'.Status::getUrl().'"', false);
});

it('says every system is operational once both checks found nothing wrong', function (): void {
    Http::fake();

    $banner = Livewire::actingAs($this->admin, 'admin')->test(ServiceStatusBanner::class)
        ->set('services', [])
        ->call('checkServices')
        ->assertSee('Still checking the AI services…')
        ->assertDontSee('All systems operational');

    expect(array_keys($banner->get('services')))->toBe(array_map(fn (Service $service): string => $service->value, Service::cases()));

    $running = ['state' => 'running', 'summary' => 'Answers.', 'facts' => [], 'providers' => []];
    $off = ['state' => 'off', 'summary' => 'Not set up.', 'facts' => [], 'providers' => []];

    $banner->set('services', ['database' => $running, 'email' => $off])
        ->set('aiServices', ['chat' => $running, 'voice' => $off])
        ->assertSee('All systems operational')
        ->assertSee('Checked at');
});

it('lists what is down or needs a look on the banner', function (): void {
    $running = ['state' => 'running', 'summary' => 'Answers.', 'facts' => [], 'providers' => []];

    Livewire::actingAs($this->admin, 'admin')->test(ServiceStatusBanner::class)
        ->set('checkedAt', now()->toIso8601String())
        ->set('services', [
            'database' => $running,
            'queue' => ['state' => 'down', 'summary' => 'Horizon is not running.', 'facts' => [], 'providers' => []],
        ])
        ->set('aiServices', ['reranking' => ['state' => 'warning', 'summary' => 'The key was turned down.', 'facts' => [], 'providers' => []]])
        ->assertSee('1 service is down')
        ->assertSeeInOrder(['Queue', 'Horizon is not running.', 'Reranking', 'The key was turned down.'])
        ->assertDontSee('Database')
        ->assertDontSee('All systems operational');
});

it('leaves the banner out for anyone who may not open the Status page', function (): void {
    expect(ServiceStatusBanner::canView())->toBeFalse();
});
