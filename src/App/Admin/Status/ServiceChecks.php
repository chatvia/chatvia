<?php

declare(strict_types=1);

namespace App\Admin\Status;

use App\Admin\Settings\HorizonWorkers;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\ServerProgram;
use App\Admin\Settings\ServerProgramFinder;
use App\Admin\Status\Data\ServiceCheckData;
use App\Admin\Status\Data\ServiceStatusData;
use Domain\Analytics\Services\AnalyticsDatabase;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Pusher\Pusher;
use Throwable;

/**
 * Checks each service of the Status page ({@see Service}) live: it talks to
 * the service the way the app does (a query, a ping, a file written and read
 * back), or reads what the process leaves behind (Horizon's supervisors,
 * Pulse's server stats). A check that throws is a service that is down, so
 * one broken service never hides the others.
 */
final readonly class ServiceChecks
{
    /**
     * The database extensions the app's schema cannot run without.
     * pg_textsearch is deliberately not here: without it, lexical search
     * falls back to the built-in full-text search (LexicalSearch).
     */
    private const array EXTENSIONS = ['vector', 'pg_trgm'];

    /** Extensions the app uses when they are there, and runs without. */
    private const array OPTIONAL_EXTENSIONS = ['pg_textsearch'];

    /** How long a network check waits before it counts the service as down. */
    private const int TIMEOUT_SECONDS = 3;

    /**
     * The scheduler wakes up every minute ({@see SchedulerHeartbeat}); this
     * long without it means it stopped. One missed minute is allowed, for a
     * busy server or a slow start.
     */
    private const int SCHEDULER_LATE_MINUTES = 3;

    /** `pulse:check` reports every 15 seconds; this long without one means it stopped. */
    private const int SERVER_STATS_LATE_SECONDS = 120;

    /** Less than this share of the disk free needs a look. */
    private const float LOW_DISK_SHARE = 0.1;

    public function __construct(
        private HorizonWorkers $horizon,
        private ServerProgramFinder $programs,
        private ModelsDevCatalog $catalog,
        private AnalyticsDatabase $analytics,
        private MasterSupervisorRepository $masters,
        private SupervisorRepository $supervisors,
        private JobRepository $jobs,
        private MetricsRepository $metrics,
    ) {}

    public function check(Service $service): ServiceStatusData
    {
        try {
            return match ($service) {
                Service::Database => $this->database(),
                Service::Redis => $this->redis(),
                Service::Queue => $this->queue(),
                Service::Scheduler => $this->scheduler(),
                Service::Realtime => $this->realtime(),
                Service::ServerMonitor => $this->serverMonitor(),
                Service::Storage => $this->storage(),
                Service::Email => $this->email(),
                Service::Programs => $this->programs(),
                Service::ModelDetails => $this->modelDetails(),
            };
        } catch (Throwable $e) {
            return new ServiceStatusData(ServiceState::Down, __('Does not answer.'), checks: [
                new ServiceCheckData(__('Reachable'), __('No'), CheckMark::Failed),
            ], hint: Str::limit($e->getMessage(), 200));
        }
    }

    private function database(): ServiceStatusData
    {
        [$version, $elapsed] = Benchmark::value(fn (): string => (string) DB::scalar('show server_version'));
        $time = $this->wholeMilliseconds($elapsed);

        /** @var array<string, string> $installed name => version */
        $installed = DB::table('pg_extension')->pluck('extversion', 'extname')->all();
        $missing = array_values(array_diff(self::EXTENSIONS, array_keys($installed)));
        // Analytics may have a database of its own, so pg_partman is looked for there.
        $partman = $this->analytics->partmanVersion();

        $checks = [
            new ServiceCheckData(__('Version'), 'PostgreSQL '.Str::before($version, ' ')),
            new ServiceCheckData(__('Response time'), __(':ms ms', ['ms' => $time])),
            ...array_map(fn (string $name): ServiceCheckData => new ServiceCheckData(
                $name,
                $installed[$name] ?? __('Not installed'),
                isset($installed[$name]) ? CheckMark::Passed : CheckMark::Failed,
            ), self::EXTENSIONS),
            ...array_map(fn (string $name): ServiceCheckData => new ServiceCheckData(
                $name,
                $installed[$name] ?? __('Not installed'),
                isset($installed[$name]) ? CheckMark::Passed : CheckMark::Skipped,
                __('Optional'),
            ), self::OPTIONAL_EXTENSIONS),
            new ServiceCheckData('pg_partman', $partman ?? __('Not installed'), $partman !== null ? CheckMark::Passed : CheckMark::Warning),
            new ServiceCheckData(
                __('Keyword search'),
                isset($installed['pg_textsearch']) ? __('pg_textsearch (BM25)') : __('Built-in full-text search'),
                CheckMark::Info,
            ),
        ];

        if ($missing !== []) {
            return new ServiceStatusData(ServiceState::Down, __('Missing extensions: :list.', ['list' => Arr::join($missing, ', ')]), checks: $checks, hint: __('Install them on the database server, then run the migrations again.'));
        }

        if ($partman === null) {
            return new ServiceStatusData(ServiceState::Warning, __('pg_partman is not installed: analytics will not work as it should.'), checks: $checks, hint: __('Analytics needs pg_partman to split its message log by month.'), docsUrl: config('chatvia.docs_url').'/guide/how-it-works/background#analytics');
        }

        return new ServiceStatusData(ServiceState::Running, __('Every extension is installed.'), checks: $checks);
    }

    private function redis(): ServiceStatusData
    {
        $redis = Redis::connection();

        $time = $this->wholeMilliseconds(Benchmark::measure(fn (): mixed => $redis->command('ping')));

        // phpredis returns one flat list, Predis one list per section.
        $info = (array) $redis->command('info');
        $read = fn (string $section, string $key): ?string => isset($info[$key]) || isset($info[$section][$key])
            ? (string) ($info[$key] ?? $info[$section][$key])
            : null;

        $version = $read('Server', 'redis_version');
        $memory = $read('Memory', 'used_memory_human');
        $clients = $read('Clients', 'connected_clients');
        $uptime = $read('Server', 'uptime_in_seconds');

        return new ServiceStatusData(ServiceState::Running, __('Answers.'), checks: array_values(array_filter([
            $version !== null ? new ServiceCheckData(__('Version'), "Redis {$version}") : null,
            new ServiceCheckData(__('Response time'), __(':ms ms', ['ms' => $time])),
            $memory !== null ? new ServiceCheckData(__('Memory in use'), $memory, CheckMark::Info) : null,
            $clients !== null ? new ServiceCheckData(__('Connections'), Number::format((int) $clients), CheckMark::Info) : null,
            $uptime !== null ? new ServiceCheckData(__('Up for'), now()->subSeconds((int) $uptime)->diffForHumans(syntax: Carbon::DIFF_ABSOLUTE), CheckMark::Info) : null,
        ])));
    }

    /** The queue as Horizon's own dashboard shows it. */
    private function queue(): ServiceStatusData
    {
        $masters = $this->masters->all();
        $failed = $this->jobs->countRecentlyFailed();

        // The jobs that wait and the ones that failed are kept in Redis, so
        // they are known while Horizon is down too.
        $counts = [
            new ServiceCheckData(__('Waiting jobs'), Number::format($this->jobs->countPending()), CheckMark::Info),
            new ServiceCheckData(__('Failed this week'), Number::format($failed), $failed > 0 ? CheckMark::Warning : CheckMark::Passed),
        ];

        if ($masters === []) {
            $stopping = $this->horizon->isStopping();
            $checks = [
                new ServiceCheckData('Horizon', $stopping ? __('Restarting') : __('Not running'), $stopping ? CheckMark::Warning : CheckMark::Failed),
                new ServiceCheckData(__('Workers'), '0', $stopping ? CheckMark::Warning : CheckMark::Failed),
                ...$counts,
            ];

            return $stopping
                ? new ServiceStatusData(ServiceState::Warning, __('Horizon is restarting.'), checks: $checks, hint: __('It starts again once its running jobs finish.'))
                : new ServiceStatusData(ServiceState::Down, __('Horizon is not running.'), checks: $checks, hint: __('No background job runs. Start it with "php artisan horizon".'));
        }

        // Each supervisor counts its workers per queue.
        $workers = collect($this->supervisors->all())
            ->sum(fn (object $supervisor): int => (int) array_sum((array) $supervisor->processes));
        $paused = collect($masters)->every(fn (object $master): bool => $master->status === 'paused');

        $checks = [
            new ServiceCheckData('Horizon', $paused ? __('Paused') : __('Running'), $paused ? CheckMark::Warning : CheckMark::Passed),
            new ServiceCheckData(__('Workers'), Number::format($workers), $workers > 0 ? CheckMark::Passed : CheckMark::Failed),
            new ServiceCheckData(__('Jobs a minute'), Number::format((float) $this->metrics->jobsProcessedPerMinute()), CheckMark::Info),
            ...$counts,
        ];

        if ($paused) {
            return new ServiceStatusData(ServiceState::Warning, __('Horizon is paused.'), checks: $checks, hint: __('Jobs wait until it goes on. Run "php artisan horizon:continue".'));
        }

        return new ServiceStatusData(ServiceState::Running, __('Runs the background jobs.'), checks: $checks);
    }

    /** When the scheduler last woke up ({@see SchedulerHeartbeat}). */
    private function scheduler(): ServiceStatusData
    {
        $ranAt = SchedulerHeartbeat::lastRunAt();
        $late = new ServiceCheckData(__('Counts as stopped after'), trans_choice(':count minute|:count minutes', self::SCHEDULER_LATE_MINUTES), CheckMark::Info);

        if (! $ranAt instanceof Carbon) {
            return new ServiceStatusData(ServiceState::Down, __('Has not run a task yet.'), checks: [
                new ServiceCheckData(__('Last task'), __('Never'), CheckMark::Failed),
                $late,
            ], hint: __('Run "php artisan schedule:work", or "schedule:run" every minute from cron.'));
        }

        $stopped = $ranAt->lt(now()->subMinutes(self::SCHEDULER_LATE_MINUTES));
        $checks = [
            new ServiceCheckData(__('Last task'), $ranAt->diffForHumans(), $stopped ? CheckMark::Failed : CheckMark::Passed),
            new ServiceCheckData(__('Ran at'), $ranAt->timezone((string) config('app.timezone'))->format('Y-m-d H:i'), CheckMark::Info),
            $late,
        ];

        if ($stopped) {
            return new ServiceStatusData(ServiceState::Down, __('No task for :time.', ['time' => $ranAt->diffForHumans(syntax: Carbon::DIFF_ABSOLUTE)]), checks: $checks, hint: __('Run "php artisan schedule:work", or "schedule:run" every minute from cron.'));
        }

        return new ServiceStatusData(ServiceState::Running, __('Runs the tasks on time.'), checks: $checks);
    }

    /**
     * Asks Reverb for its open channels, the way the app sends it a message:
     * at the same address, signed with the app's own keys.
     */
    private function realtime(): ServiceStatusData
    {
        $driver = (string) config('broadcasting.default');

        if ($driver !== 'reverb') {
            return new ServiceStatusData(ServiceState::Off, __('Not on Reverb.'), checks: [
                new ServiceCheckData(__('Driver'), Str::headline($driver), CheckMark::Skipped),
            ], hint: __('Live updates go through ":driver".', ['driver' => $driver]));
        }

        $config = (array) config('broadcasting.connections.reverb');
        $options = (array) ($config['options'] ?? []);
        $address = ($options['scheme'] ?? 'https').'://'.($options['host'] ?? 'localhost').':'.($options['port'] ?? 443);
        $path = "/apps/{$config['app_id']}/channels";
        $addressCheck = fn (CheckMark $mark): ServiceCheckData => new ServiceCheckData(__('Address'), Str::after($address, '://'), $mark);

        try {
            [$response, $elapsed] = Benchmark::value(fn (): Response => Http::withOptions((array) ($config['client_options'] ?? []))
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::TIMEOUT_SECONDS)
                ->get($address.$path, Pusher::build_auth_query_params((string) $config['key'], (string) $config['secret'], 'GET', $path)));
            $time = $this->wholeMilliseconds($elapsed);
        } catch (ConnectionException) {
            return new ServiceStatusData(ServiceState::Down, __('Reverb does not answer.'), checks: [
                $addressCheck(CheckMark::Failed),
            ], hint: __('Start it with "php artisan reverb:start".'));
        }

        if (! $response->successful()) {
            return new ServiceStatusData(ServiceState::Down, __('Reverb refused the app keys.'), checks: [
                $addressCheck(CheckMark::Passed),
                new ServiceCheckData(__('Response time'), __(':ms ms', ['ms' => $time])),
                new ServiceCheckData(__('App keys'), __('Refused (:status)', ['status' => $response->status()]), CheckMark::Failed),
            ], hint: __('Check REVERB_APP_ID, REVERB_APP_KEY and REVERB_APP_SECRET.'));
        }

        // No open channels come back as [], a list, not an object.
        $channels = count((array) $response->json('channels', []));

        return new ServiceStatusData(ServiceState::Running, __('Reverb answers.'), checks: [
            $addressCheck(CheckMark::Passed),
            new ServiceCheckData(__('Response time'), __(':ms ms', ['ms' => $time])),
            new ServiceCheckData(__('App keys'), __('Accepted')),
            new ServiceCheckData(__('Open channels'), Number::format($channels), CheckMark::Info),
        ]);
    }

    /** Pulse's own database, and the server stats `pulse:check` sends. */
    private function serverMonitor(): ServiceStatusData
    {
        if (! (bool) config('pulse.enabled')) {
            return new ServiceStatusData(ServiceState::Off, __('Pulse is off.'), checks: [
                new ServiceCheckData('Pulse', __('Off'), CheckMark::Skipped),
            ], hint: __('Turn it on with PULSE_ENABLED.'));
        }

        $values = DB::connection(config('pulse.storage.database.connection'))->table('pulse_values')->where('type', 'system');
        $last = (clone $values)->max('timestamp');
        $servers = (clone $values)->count();
        $recording = new ServiceCheckData('Pulse', __('Recording'));

        if (! is_numeric($last)) {
            return new ServiceStatusData(ServiceState::Warning, __('No server stats yet.'), checks: [
                $recording,
                new ServiceCheckData(__('Servers'), '0', CheckMark::Warning),
                new ServiceCheckData(__('Last report'), __('Never'), CheckMark::Warning),
            ], hint: __('No server has sent its CPU, memory and disk yet. Run "php artisan pulse:check".'));
        }

        $reportedAt = Carbon::createFromTimestamp((int) $last);
        $stopped = $reportedAt->lt(now()->subSeconds(self::SERVER_STATS_LATE_SECONDS));
        $checks = [
            $recording,
            new ServiceCheckData(__('Servers'), Number::format($servers), CheckMark::Info),
            new ServiceCheckData(__('Last report'), $reportedAt->diffForHumans(), $stopped ? CheckMark::Warning : CheckMark::Passed),
        ];

        if ($stopped) {
            return new ServiceStatusData(ServiceState::Warning, __('Server stats stopped.'), checks: $checks, hint: __('Pulse records, but the server stats stopped. Is "php artisan pulse:check" running?'));
        }

        return new ServiceStatusData(ServiceState::Running, __('Records requests, jobs and stats.'), checks: $checks);
    }

    /** Writes a small file to each disk, reads it back and deletes it. */
    private function storage(): ServiceStatusData
    {
        $checks = [];
        $local = false;

        foreach (['private' => __('Private files'), 'assets' => __('Public files'), 'backups' => __('Backups')] as $disk => $label) {
            $config = (array) config("filesystems.disks.{$disk}");
            $s3 = ($config['driver'] ?? '') === 's3';
            $local = $local || ! $s3;
            $where = $s3 ? 'S3 · '.($config['bucket'] ?? '') : __('This server');

            $path = 'status-check/'.Str::uuid7().'.txt';
            $files = Storage::disk($disk);

            try {
                $saved = $files->put($path, 'ok') && $files->get($path) === 'ok';
            } finally {
                $files->delete($path);
            }

            $checks[] = new ServiceCheckData($label, $where, $saved ? CheckMark::Passed : CheckMark::Failed);

            if (! $saved) {
                return new ServiceStatusData(ServiceState::Down, __('The :disk disk failed.', ['disk' => $disk]), checks: $checks, hint: __('It could not save a file and read it back.'));
            }
        }

        $free = $local ? @disk_free_space(storage_path()) : false;
        $total = $local ? @disk_total_space(storage_path()) : false;

        if ($free !== false && $total !== false && $total > 0) {
            $checks[] = new ServiceCheckData(
                __('Free space'),
                __(':free of :total', ['free' => Number::fileSize($free, 1), 'total' => Number::fileSize($total, 1)]),
                $free / $total < self::LOW_DISK_SHARE ? CheckMark::Warning : CheckMark::Passed,
            );
        }

        return new ServiceStatusData(ServiceState::Running, __('Files are saved and read back.'), checks: $checks);
    }

    /** The mailer in use; an SMTP server is asked whether it answers, without sending. */
    private function email(): ServiceStatusData
    {
        $mailer = (string) config('mail.default');
        $config = (array) config("mail.mailers.{$mailer}");
        $transport = (string) ($config['transport'] ?? $mailer);
        $sender = (string) config('mail.from.address');
        $from = new ServiceCheckData(__('Sender'), filled($sender) ? $sender : __('Not set'), filled($sender) ? CheckMark::Passed : CheckMark::Warning);

        if (collect(['log', 'array'])->containsStrict($transport)) {
            return new ServiceStatusData(ServiceState::Warning, __('Emails go to the log.'), checks: [
                new ServiceCheckData(__('Mailer'), Str::headline($transport), CheckMark::Warning),
                $from,
            ], hint: __('They are not sent. The sign-in and password links in them are written to the log file, so keep it private. Set up email in Settings → Email.'));
        }

        $mailerCheck = new ServiceCheckData(__('Mailer'), $transport === 'smtp' ? 'SMTP' : Str::headline($transport));

        if ($transport !== 'smtp') {
            return new ServiceStatusData(ServiceState::Running, __('Sends with :service.', ['service' => Str::headline($transport)]), checks: [
                $mailerCheck,
                $from,
            ], hint: __('It is not tested here, as that would send an email.'));
        }

        $address = ($config['host'] ?? '').':'.($config['port'] ?? 587);
        $socket = @stream_socket_client("tcp://{$address}", $code, $message, self::TIMEOUT_SECONDS);

        if ($socket === false) {
            return new ServiceStatusData(ServiceState::Down, __('The mail server does not answer.'), checks: [
                $mailerCheck,
                $from,
                new ServiceCheckData(__('Mail server'), $address, CheckMark::Failed),
            ], hint: __('Check the server and port in Settings → Email.'));
        }

        fclose($socket);

        return new ServiceStatusData(ServiceState::Running, __('The mail server answers.'), checks: [
            $mailerCheck,
            $from,
            new ServiceCheckData(__('Mail server'), $address),
        ]);
    }

    /**
     * The models.dev catalog each model's facts come from (does it reason,
     * which effort values, does it take a temperature). Not reaching it
     * needs a look, never more: models then keep their "Reasons" switch.
     */
    private function modelDetails(): ServiceStatusData
    {
        $listed = $this->catalog->listedModels();

        if ($listed === 0) {
            return new ServiceStatusData(ServiceState::Warning, __('Could not read models.dev.'),
                checks: [new ServiceCheckData(__('Reachable'), __('No'), CheckMark::Failed)], hint: __('Models keep their "Reasons" switch until it can be read. It is tried again once a day.'));
        }

        return new ServiceStatusData(ServiceState::Running, __('Model details loaded.'), [__(':count models listed', ['count' => Number::format($listed)])], checks: [
            new ServiceCheckData(__('Models listed'), Number::format($listed)),
        ]);
    }

    private function programs(): ServiceStatusData
    {
        $keys = PlatformSettings::configKeys();

        $missing = array_values(array_filter(
            ServerProgram::cases(),
            fn (ServerProgram $program): bool => $this->programs->find((string) config($keys[$program->property()][0]->key)) === null,
        ));

        // What each missing program costs. Without some, the app does the
        // work itself, or they are not used now (another PDF maker, speech
        // to text off): they cost nothing.
        $losses = [];

        foreach ($missing as $program) {
            $loss = $program->lossWhenMissing();

            if ($loss !== null) {
                $losses[$program->value] = $loss;
            }
        }

        $losing = array_values(array_filter($missing, fn (ServerProgram $program): bool => isset($losses[$program->value])));

        // Every program is optional, so what one costs is told, not
        // warned about: the card stays running and the dashboard banner
        // leaves it out. Those lines come first, so the card shows them.
        $checks = array_map(fn (ServerProgram $program): ServiceCheckData => match (true) {
            ! collect($missing)->containsStrict($program) => new ServiceCheckData($program->value, __('Found')),
            isset($losses[$program->value]) => new ServiceCheckData($program->value, __('Not found'), CheckMark::Info, __('Optional'), $losses[$program->value]),
            $program->hasBuiltinFallback() => new ServiceCheckData($program->value, __('Not found'), CheckMark::Skipped, __('Built-in used')),
            default => new ServiceCheckData($program->value, __('Not found'), CheckMark::Skipped, __('Not needed now')),
        }, [...$losing, ...array_filter(ServerProgram::cases(), fn (ServerProgram $program): bool => ! collect($losing)->containsStrict($program))]);

        $names = fn (array $programs): string => Arr::join(array_map(fn (ServerProgram $program): string => $program->value, $programs), ', ');

        if ($losing !== []) {
            // Only a program that reads uploads makes file types unreadable;
            // the others make PDFs or prepare recordings for speech to text.
            $summary = array_any($losing, fn (ServerProgram $program): bool => $program->readsUploads())
                ? __('Some file types cannot be read.')
                : __('Some features do not work.');

            return new ServiceStatusData(ServiceState::Running, $summary, checks: $checks, hint: __('Not found: :list. Programs are optional. For each one, install it on the server, set its path in Settings → Platform → Programs, or leave it out and lose only what it does.', ['list' => $names($losing)]));
        }

        if ($missing !== []) {
            // Why each missing one costs nothing: the app does its work
            // itself, or the settings in use do not run it.
            $builtin = array_values(array_filter($missing, fn (ServerProgram $program): bool => $program->hasBuiltinFallback()));
            $unused = array_values(array_filter($missing, fn (ServerProgram $program): bool => ! $program->hasBuiltinFallback()));

            $hint = match (true) {
                $unused === [] => __('Not found: :list. The app does this work itself.', ['list' => $names($builtin)]),
                $builtin === [] => __('Not found: :list. Not used with the current settings.', ['list' => $names($unused)]),
                default => __('Not found: :list. The app does the work of :builtin itself, and the current settings do not use :unused.', [
                    'list' => $names($missing),
                    'builtin' => $names($builtin),
                    'unused' => $names($unused),
                ]),
            };

            return new ServiceStatusData(ServiceState::Running, __('Every program needed is found.'), checks: $checks, hint: $hint);
        }

        return new ServiceStatusData(ServiceState::Running, __('Every program is found.'), checks: $checks);
    }

    private function wholeMilliseconds(float $milliseconds): int
    {
        return max(1, (int) round($milliseconds));
    }
}
