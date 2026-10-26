<?php

use App\Admin\Clusters\Settings\SettingsCluster;
use App\Admin\Pages\Dashboard;
use App\Admin\Pages\Status;
use App\Admin\Pages\Tools;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Status\AiService;
use App\Admin\Status\AiServiceChecks;
use App\Admin\Status\CheckMark;
use App\Admin\Status\Data\ServiceCheckData;
use App\Admin\Status\Data\ServiceStatusData;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;
use App\Admin\Widgets\ServiceStatusBanner;
use Domain\Analytics\Services\AnalyticsDatabase;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\Admin\FakeRedisAndHorizon;

/*
 * The admin panel's Status page: whether the services the install needs are
 * up (database, Redis, queue, scheduler, Reverb, Pulse, storage, email,
 * programs), whether the AI and other outside services accept their keys,
 * and the environment as `php artisan about` tells it. It sits below Tools
 * and opens for the same staff.
 */

beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
    Storage::fake('private');
    Storage::fake('public');
    FakeRedisAndHorizon::bind();
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
        ->assertDontSee('display: none', false)
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
        ->assertSeeInOrder(['PostgreSQL', 'Running', 'Chat models', 'Set up']);
});

it('lists the model catalog with the AI and other services, not as a service card', function (): void {
    $listed = ['state' => 'running', 'summary' => 'Model details loaded.', 'facts' => ['686 models listed'], 'providers' => []];

    Livewire::actingAs($this->admin, 'admin')->test(Status::class)
        ->set('checkedAt', now()->toIso8601String())
        ->set('services', [Service::ModelDetails->value => $listed])
        ->assertSeeInOrder(['AI and other services', 'Chat models', 'Model catalog', 'What each model supports, from', '686 models listed', 'Environment'])
        ->assertDontSeeHtml('<article class="cv-service" data-service="model_details"')
        ->assertSeeHtml('<a href="https://models.dev" target="_blank" rel="noopener noreferrer" class="cv-service__link">models.dev</a>');
});

it('marks an outside service that is set up with a check, and links each to its card in Settings', function (): void {
    $running = ['state' => 'running', 'summary' => 'Connected.', 'facts' => [], 'providers' => []];
    $off = ['state' => 'off', 'summary' => 'Not set up.', 'facts' => [], 'providers' => []];

    $page = Livewire::actingAs($this->admin, 'admin')->test(Status::class)
        ->set('checkedAt', now()->toIso8601String())
        ->set('services', ['database' => $running])
        ->set('aiServices', ['chat' => $running, 'web_pages' => $off])
        ->assertSeeHtml('<span class="cv-state-check" title="Set up">')
        ->assertSeeInOrder(['Web pages', 'Off', 'Change']);

    foreach (AiService::cases() as $service) {
        $page->assertSeeHtml('href="'.e($service->settingsUrl()).'"');
    }

    expect(AiService::Chat->settingsUrl())->toEndWith('/settings/ai-models#ai-models-models')
        ->and(AiService::WebPages->settingsUrl())->toEndWith('/settings/knowledge#knowledge-website_import');
});

it('finds the database with the extensions the search needs', function (): void {
    $this->mock(AnalyticsDatabase::class)->shouldReceive('partmanVersion')->andReturn('5.2.4');

    $status = app(ServiceChecks::class)->check(Service::Database);

    expect($status->state)->toBe(ServiceState::Running)
        ->and($status->checks[0]->value)->toStartWith('PostgreSQL ');
});

it('lists the database extensions with a check mark or a cross', function (): void {
    $status = app(ServiceChecks::class)->check(Service::Database);
    $checks = collect($status->checks)->keyBy('label');

    expect($checks['vector']->mark)->toBe(CheckMark::Passed)
        ->and($checks['pg_trgm']->mark)->toBe(CheckMark::Passed)
        ->and($checks['pg_textsearch']->tag)->toBe('Optional')
        ->and($checks['pg_textsearch']->mark)->toBeIn([CheckMark::Passed, CheckMark::Skipped]);

    Livewire::actingAs($this->admin, 'admin')->test(Status::class)
        ->set('checkedAt', now()->toIso8601String())
        ->set('services', ['database' => [
            'state' => 'down',
            'summary' => 'Missing extensions: pg_trgm.',
            'checks' => [
                ['label' => 'vector', 'value' => '0.8.1', 'mark' => 'passed', 'tag' => null],
                ['label' => 'pg_trgm', 'value' => 'Not installed', 'mark' => 'failed', 'tag' => null],
                ['label' => 'pg_textsearch', 'value' => 'Not installed', 'mark' => 'skipped', 'tag' => 'Optional'],
            ],
        ]])->assertSeeInOrder(['cv-check--passed', 'vector', '0.8.1'], false)->assertSeeHtmlInOrder(['cv-check--failed', 'pg_trgm', 'Not installed'])->assertSeeHtmlInOrder(['cv-check--skipped', 'pg_textsearch', 'Optional', 'Not installed']);
});

it('looks for pg_partman in the analytics database, and warns without it', function (?string $version, ServiceState $state, CheckMark $mark): void {
    $this->mock(AnalyticsDatabase::class)->shouldReceive('partmanVersion')->andReturn($version);

    $status = app(ServiceChecks::class)->check(Service::Database);
    $checks = collect($status->checks)->keyBy('label');

    expect($status->state)->toBe($state)
        ->and($checks['pg_partman']->mark)->toBe($mark)
        ->and($checks['pg_partman']->value)->toBe($version ?? 'Not installed');

    if ($version === null) {
        expect($status->summary)->toBe('pg_partman is not installed: analytics will not work as it should.')
            ->and($status->docsUrl)->toEndWith('/guide/how-it-works/background#analytics');
    }
})->with([
    'installed' => ['5.2.4', ServiceState::Running, CheckMark::Passed],
    'missing' => [null, ServiceState::Warning, CheckMark::Warning],
]);

it('gives every service card its check lines', function (): void {
    Http::fake();
    config(['mail.default' => 'log']);

    foreach (Service::cases() as $service) {
        expect(app(ServiceChecks::class)->check($service)->checks)->not->toBeEmpty();
    }
});

it('reads Redis and the queue from what they answer', function (): void {
    $redis = app(ServiceChecks::class)->check(Service::Redis);
    $queue = app(ServiceChecks::class)->check(Service::Queue);

    expect($redis->state)->toBe(ServiceState::Running)
        ->and($redis->checks[0]->value)->toBe('Redis 7.4.0')
        ->and($queue->state)->toBe(ServiceState::Down)
        ->and($queue->summary)->toBe('Horizon is not running.');
});

it('shows four lines on a card, problems first, and the rest behind "Show all"', function (): void {
    $line = fn (string $label, string $mark = 'passed'): array => ['label' => $label, 'value' => 'ok', 'mark' => $mark, 'tag' => null];
    $programs = [
        'state' => 'warning',
        'summary' => 'Not found: catdoc.',
        'checks' => [$line('pdfinfo'), $line('pdftotext'), $line('pandoc'), $line('ffmpeg'), $line('ffprobe'), $line('catdoc', 'warning')],
    ];

    expect(array_map(fn (ServiceCheckData $check): string => $check->label, ServiceStatusData::from($programs)->cardChecks()))
        ->toBe(['catdoc', 'pdfinfo', 'pdftotext', 'pandoc']);

    Livewire::actingAs($this->admin, 'admin')->test(Status::class)
        ->set('checkedAt', now()->toIso8601String())
        ->set('services', ['programs' => $programs, 'redis' => ['state' => 'running', 'summary' => 'Answers.', 'checks' => [$line('Version')]]])
        ->assertSee('Show all 6')
        ->assertDontSee('Show all 1');
});

it('opens the details of a card with a hint and few lines', function (): void {
    Livewire::actingAs($this->admin, 'admin')->test(Status::class)
        ->set('checkedAt', now()->toIso8601String())
        ->set('services', ['queue' => [
            'state' => 'down',
            'summary' => 'Horizon is not running.',
            'hint' => 'Start it with "php artisan horizon".',
            'checks' => [['label' => 'Horizon', 'value' => 'Not running', 'mark' => 'failed', 'tag' => null]],
        ]])
        ->assertSeeInOrder(['Details', 'Horizon is not running.', 'Start it with'])
        ->assertDontSee('Show all');
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
        ->and($status->summary)->toBe('Reverb does not answer.')
        ->and($status->checks[0]->value)->toBe('127.0.0.1:1');
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
        ->and(collect($running->checks)->firstWhere('label', 'Open channels')->value)->toBe('0');

    $refused = app(ServiceChecks::class)->check(Service::Realtime);
    expect($refused->state)->toBe(ServiceState::Down)
        ->and($refused->summary)->toBe('Reverb refused the app keys.')
        ->and(collect($refused->checks)->firstWhere('label', 'App keys')->value)->toBe('Refused (401)');

    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), 'reverb.test')
        && Str::contains($request->url(), '/apps/test-app-id/channels?')
        && Str::contains($request->url(), 'auth_signature='));
});

it('warns when email goes to the log, and finds an SMTP server that does not answer', function (): void {
    config(['mail.default' => 'log']);
    expect(app(ServiceChecks::class)->check(Service::Email)->state)->toBe(ServiceState::Warning);

    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
    expect(app(ServiceChecks::class)->check(Service::Email)->state)->toBe(ServiceState::Down);
});

it('names the programs that are not found, and stays running as they are optional', function (): void {
    config(['services.bin.pdftotext' => '/no/such/folder/pdftotext', 'chatvia.ai.document_parser' => 'none']);

    $status = app(ServiceChecks::class)->check(Service::Programs);
    $pdftotext = collect($status->checks)->firstWhere('label', 'pdftotext');

    expect($status->state)->toBe(ServiceState::Running)
        ->and($status->summary)->toBe('Some file types cannot be read.')
        ->and($status->hint)->toContain('pdftotext')
        ->and($status->hint)->toContain('Programs are optional.')
        ->and($pdftotext->mark)->toBe(CheckMark::Info)
        ->and($pdftotext->tag)->toBe('Optional')
        ->and($pdftotext->note)->toStartWith('PDF files cannot be trained again');
});

it('says a missing PDF maker costs PDFs, not file types', function (): void {
    foreach (['pdfinfo', 'pdftotext', 'pandoc', 'catdoc', 'catppt', 'ffmpeg', 'ffprobe'] as $program) {
        config(["services.bin.{$program}" => '/bin/sh']);
    }

    config([
        'laravel-pdf.weasyprint.binary' => '/no/such/folder/weasyprint',
        'laravel-pdf.driver' => 'weasyprint',
    ]);

    $status = app(ServiceChecks::class)->check(Service::Programs);

    expect($status->state)->toBe(ServiceState::Running)
        ->and($status->summary)->toBe('Some features do not work.')
        ->and($status->hint)->toStartWith('Not found: weasyprint. Programs are optional.')
        ->and($status->checks[0]->label)->toBe('weasyprint')
        ->and($status->checks[0]->note)->toContain('pick Cloudflare or Gotenberg');
});

it('shows what a missing program costs under its line', function (): void {
    $programs = [
        'state' => 'running',
        'summary' => 'Some file types cannot be read.',
        'hint' => 'Not found: ffmpeg. Programs are optional.',
        'checks' => [
            ['label' => 'ffmpeg', 'value' => 'Not found', 'mark' => 'info', 'tag' => 'Optional', 'note' => 'Recordings over 25 MB cannot be turned into text.'],
            ['label' => 'pdfinfo', 'value' => 'Not found', 'mark' => 'skipped', 'tag' => 'Not needed now'],
        ],
    ];

    Livewire::actingAs($this->admin, 'admin')->test(Status::class)
        ->set('checkedAt', now()->toIso8601String())
        ->set('services', ['programs' => $programs])
        ->assertSeeInOrder(['cv-check--noted', 'ffmpeg', 'Optional', 'Not found', 'cv-check__note', 'Recordings over 25 MB cannot be turned into text.'], false)
        ->assertSeeInOrder(['Programs are optional.', 'ffmpeg', 'Recordings over 25 MB cannot be turned into text.']);
});

it('says the app does the work itself when only programs it can replace are not found', function (): void {
    // Found: any program file will do.
    foreach (['pdftotext', 'ffmpeg'] as $program) {
        config(["services.bin.{$program}" => '/bin/sh']);
    }

    config(['laravel-pdf.weasyprint.binary' => '/bin/sh']);

    // The app reads every document format itself, so these cost nothing.
    foreach (['pdfinfo', 'pandoc', 'catdoc', 'catppt', 'ffprobe'] as $program) {
        config(["services.bin.{$program}" => "/no/such/folder/{$program}"]);
    }

    $status = app(ServiceChecks::class)->check(Service::Programs);

    expect($status->state)->toBe(ServiceState::Running)
        ->and($status->summary)->toBe('Every program needed is found.')
        ->and($status->hint)->toBe('Not found: pdfinfo, pandoc, catdoc, catppt, ffprobe. The app does this work itself.')
        ->and(collect($status->checks)->firstWhere('label', 'catdoc')->tag)->toBe('Built-in used');
});

it('says the settings do not use a missing program', function (): void {
    foreach (['pdfinfo', 'pdftotext', 'pandoc', 'catdoc', 'catppt', 'ffmpeg', 'ffprobe'] as $program) {
        config(["services.bin.{$program}" => '/bin/sh']);
    }

    config([
        'laravel-pdf.weasyprint.binary' => '/no/such/folder/weasyprint',
        'laravel-pdf.driver' => 'cloudflare',
    ]);

    $status = app(ServiceChecks::class)->check(Service::Programs);

    expect($status->state)->toBe(ServiceState::Running)
        ->and($status->hint)->toBe('Not found: weasyprint. Not used with the current settings.')
        ->and(collect($status->checks)->firstWhere('label', 'weasyprint')->tag)->toBe('Not needed now');
});

it('tells apart programs the app replaces from programs the settings do not use', function (): void {
    foreach (['pdftotext', 'ffmpeg'] as $program) {
        config(["services.bin.{$program}" => '/bin/sh']);
    }

    foreach (['pdfinfo', 'pandoc', 'catdoc', 'catppt', 'ffprobe'] as $program) {
        config(["services.bin.{$program}" => "/no/such/folder/{$program}"]);
    }

    config([
        'laravel-pdf.weasyprint.binary' => '/no/such/folder/weasyprint',
        'laravel-pdf.driver' => 'cloudflare',
    ]);

    $status = app(ServiceChecks::class)->check(Service::Programs);

    expect($status->state)->toBe(ServiceState::Running)
        ->and($status->summary)->toBe('Every program needed is found.')
        ->and($status->hint)->toBe('Not found: pdfinfo, pandoc, catdoc, catppt, ffprobe, weasyprint. The app does the work of pdfinfo, pandoc, catdoc, catppt, ffprobe itself, and the current settings do not use weasyprint.');
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

it('says visitor countries cannot be found while IP2Location has no key', function (): void {
    config(['services.ip2location.key' => null]);

    $status = app(AiServiceChecks::class)->check(AiService::VisitorCountry);

    expect($status->state)->toBe(ServiceState::Off)
        ->and($status->summary)->toBe('Not set up. The country of visitors cannot be found.');
});

it('checks the IP2Location key with one lookup, the key as a bearer token', function (): void {
    config(['services.ip2location.key' => 'ip2-key', 'services.ip2location.endpoint' => 'https://api.ip2location.io']);
    Http::fake(['api.ip2location.io/*' => Http::sequence()
        ->push(['ip' => '8.8.8.8', 'country_code' => 'US'])
        ->push(['error' => ['error_code' => 10000]], 401)]);

    $status = app(AiServiceChecks::class)->check(AiService::VisitorCountry);

    expect($status->state)->toBe(ServiceState::Running)
        ->and($status->facts)->toBe(['IP2Location']);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer ip2-key')
        && ! Str::contains($request->url(), 'ip2-key'));

    expect(app(AiServiceChecks::class)->check(AiService::VisitorCountry)->state)->toBe(ServiceState::Down);
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

    expect(config('services.openrouter.api_key'))->toBe('sk-or-reloaded')
        ->and(Cache::has('settings.'.AiProviderSettings::cacheKey()))->toBeTrue();
});

/*
 * The dashboard's status alert: one line, only while a service is down,
 * naming those services and linking to this page.
 */

it('puts a hidden status alert on the dashboard that checks once it is open', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('$wire.checkServices()', false)
        ->assertDontSee('View status');
});

it('shows nothing while every service is running or only needs a look', function (): void {
    Http::fake();

    $banner = Livewire::actingAs($this->admin, 'admin')->test(ServiceStatusBanner::class)
        ->set('services', [])
        ->call('checkServices');

    expect(array_keys($banner->get('services')))->toBe(array_map(fn (Service $service): string => $service->value, Service::cases()));

    $running = ['state' => 'running', 'summary' => 'Answers.', 'facts' => [], 'providers' => []];
    $warning = ['state' => 'warning', 'summary' => 'Emails go to the log.', 'facts' => [], 'providers' => []];

    $banner->set('services', ['database' => $running, 'email' => $warning])
        ->set('aiServices', ['reranking' => $warning])
        ->assertSee('display: none', false)
        ->assertDontSee('View status')
        ->assertDontSee('Emails go to the log.');
});

it('names only the services that are down on the alert', function (): void {
    $running = ['state' => 'running', 'summary' => 'Answers.', 'facts' => [], 'providers' => []];
    $down = ['state' => 'down', 'summary' => 'Horizon is not running.', 'facts' => [], 'providers' => []];

    Livewire::actingAs($this->admin, 'admin')->test(ServiceStatusBanner::class)
        ->set('services', [
            'database' => $running,
            'queue' => $down,
            'email' => ['state' => 'warning', 'summary' => 'Emails go to the log.', 'facts' => [], 'providers' => []],
        ])
        ->set('aiServices', ['reranking' => $down])
        ->assertDontSee('display: none', false)
        ->assertSee('2 services are down')
        ->assertSeeInOrder(['Laravel Horizon', 'Reranking', 'View status'])
        ->assertSee('href="'.Status::getUrl().'"', false)
        ->assertDontSee('Horizon is not running.')
        ->assertDontSee('PostgreSQL')
        ->assertDontSee('Email');
});

it('leaves the banner out for anyone who may not open the Status page', function (): void {
    expect(ServiceStatusBanner::canView())->toBeFalse();
});
