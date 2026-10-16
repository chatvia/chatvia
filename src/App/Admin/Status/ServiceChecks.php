<?php

declare(strict_types=1);

namespace App\Admin\Status;

use App\Admin\Settings\HorizonWorkers;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\ServerProgram;
use App\Admin\Settings\ServerProgramFinder;
use App\Admin\Status\Data\ServiceStatusData;
use Illuminate\Http\Client\ConnectionException;
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

    /** How long a network check waits before it counts the service as down. */
    private const int TIMEOUT_SECONDS = 3;

    /**
     * The scheduler runs `horizon:snapshot` every five minutes
     * (routes/console.php), so a snapshot older than this means it stopped.
     */
    private const int SCHEDULER_LATE_MINUTES = 11;

    /** `pulse:check` reports every 15 seconds; this long without one means it stopped. */
    private const int SERVER_STATS_LATE_SECONDS = 120;

    public function __construct(
        private HorizonWorkers $horizon,
        private ServerProgramFinder $programs,
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
            };
        } catch (Throwable $e) {
            return new ServiceStatusData(ServiceState::Down, Str::limit($e->getMessage(), 200));
        }
    }

    private function database(): ServiceStatusData
    {
        $started = hrtime(true);
        $version = (string) DB::scalar('show server_version');
        $time = $this->millisecondsSince($started);

        $installed = DB::table('pg_extension')->pluck('extname')->all();
        $missing = array_values(array_diff(self::EXTENSIONS, $installed));

        $facts = [
            'PostgreSQL '.Str::before($version, ' '),
            __(':ms ms', ['ms' => $time]),
            in_array('pg_textsearch', $installed, true)
                ? __('Keyword search: pg_textsearch (BM25)')
                : __('Keyword search: built-in full-text search'),
        ];

        if ($missing !== []) {
            return new ServiceStatusData(ServiceState::Down, __('Missing extensions: :list.', ['list' => implode(', ', $missing)]), $facts);
        }

        return new ServiceStatusData(ServiceState::Running, __('Answers, with every extension the search needs.'), $facts);
    }

    private function redis(): ServiceStatusData
    {
        $redis = Redis::connection();

        $started = hrtime(true);
        $redis->command('ping');
        $time = $this->millisecondsSince($started);

        // phpredis returns one flat list, Predis one list per section.
        $info = (array) $redis->command('info');
        $version = $info['redis_version'] ?? $info['Server']['redis_version'] ?? null;
        $memory = $info['used_memory_human'] ?? $info['Memory']['used_memory_human'] ?? null;

        return new ServiceStatusData(ServiceState::Running, __('Answers.'), array_values(array_filter([
            $version !== null ? 'Redis '.$version : null,
            __(':ms ms', ['ms' => $time]),
            $memory !== null ? __(':size in use', ['size' => $memory]) : null,
        ])));
    }

    /** The queue as Horizon's own dashboard shows it. */
    private function queue(): ServiceStatusData
    {
        $masters = app(MasterSupervisorRepository::class)->all();

        if ($masters === []) {
            return $this->horizon->isStopping()
                ? new ServiceStatusData(ServiceState::Warning, __('Horizon is restarting. It starts again once its running jobs finish.'))
                : new ServiceStatusData(ServiceState::Down, __('Horizon is not running, so no background job runs. Start it with "php artisan horizon".'));
        }

        // Each supervisor counts its workers per queue.
        $workers = collect(app(SupervisorRepository::class)->all())
            ->sum(fn (object $supervisor): int => (int) array_sum((array) $supervisor->processes));

        $facts = [
            trans_choice(':count worker|:count workers', $workers),
            __(':count jobs a minute', ['count' => Number::format((float) app(MetricsRepository::class)->jobsProcessedPerMinute())]),
            __(':count waiting', ['count' => Number::format(app(JobRepository::class)->countPending())]),
            __(':count failed this week', ['count' => Number::format(app(JobRepository::class)->countRecentlyFailed())]),
        ];

        if (collect($masters)->every(fn (object $master): bool => $master->status === 'paused')) {
            return new ServiceStatusData(ServiceState::Warning, __('Horizon is paused: jobs wait until it goes on.'), $facts);
        }

        return new ServiceStatusData(ServiceState::Running, __('Horizon runs the background jobs.'), $facts);
    }

    /** When the scheduler last ran a task, from the snapshot Horizon takes on its schedule. */
    private function scheduler(): ServiceStatusData
    {
        $last = Redis::connection('horizon')->command('get', ['last_snapshot_at']);

        if (! is_numeric($last)) {
            return new ServiceStatusData(ServiceState::Warning, __('Has not run a task yet. Run "php artisan schedule:work", or "schedule:run" every minute from cron.'));
        }

        $ranAt = Carbon::createFromTimestamp((int) $last);
        $facts = [__('Last task :time', ['time' => $ranAt->diffForHumans()])];

        if ($ranAt->lt(now()->subMinutes(self::SCHEDULER_LATE_MINUTES))) {
            return new ServiceStatusData(ServiceState::Down, __('Stopped: no task has run for :time. Is "schedule:work" running?', ['time' => $ranAt->diffForHumans(syntax: Carbon::DIFF_ABSOLUTE)]), $facts);
        }

        return new ServiceStatusData(ServiceState::Running, __('Runs the repeating tasks on time.'), $facts);
    }

    /**
     * Asks Reverb for its open channels, the way the app sends it a message:
     * at the same address, signed with the app's own keys.
     */
    private function realtime(): ServiceStatusData
    {
        $driver = (string) config('broadcasting.default');

        if ($driver !== 'reverb') {
            return new ServiceStatusData(ServiceState::Off, __('Live updates go through ":driver", not Reverb.', ['driver' => $driver]));
        }

        $config = (array) config('broadcasting.connections.reverb');
        $options = (array) ($config['options'] ?? []);
        $address = ($options['scheme'] ?? 'https').'://'.($options['host'] ?? 'localhost').':'.($options['port'] ?? 443);
        $path = '/apps/'.$config['app_id'].'/channels';

        try {
            $started = hrtime(true);
            $response = Http::withOptions((array) ($config['client_options'] ?? []))
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::TIMEOUT_SECONDS)
                ->get($address.$path, Pusher::build_auth_query_params((string) $config['key'], (string) $config['secret'], 'GET', $path));
            $time = $this->millisecondsSince($started);
        } catch (ConnectionException) {
            return new ServiceStatusData(ServiceState::Down, __('Reverb does not answer at :address. Start it with "php artisan reverb:start".', ['address' => $address]));
        }

        if (! $response->successful()) {
            return new ServiceStatusData(ServiceState::Down, __('Reverb answers at :address, but refused the request (:status). Check REVERB_APP_ID, REVERB_APP_KEY and REVERB_APP_SECRET.', ['address' => $address, 'status' => $response->status()]));
        }

        // No open channels come back as [], a list, not an object.
        $channels = count((array) $response->json('channels', []));

        return new ServiceStatusData(ServiceState::Running, __('Reverb answers at :address.', ['address' => $address]), [
            __(':ms ms', ['ms' => $time]),
            trans_choice(':count open channel|:count open channels', $channels),
        ]);
    }

    /** Pulse's own database, and the server stats `pulse:check` sends. */
    private function serverMonitor(): ServiceStatusData
    {
        if (! (bool) config('pulse.enabled')) {
            return new ServiceStatusData(ServiceState::Off, __('Pulse is turned off (PULSE_ENABLED).'));
        }

        $values = DB::connection(config('pulse.storage.database.connection'))->table('pulse_values')->where('type', 'system');
        $last = (clone $values)->max('timestamp');
        $servers = (clone $values)->count();

        if (! is_numeric($last)) {
            return new ServiceStatusData(ServiceState::Warning, __('Pulse records, but no server has sent its CPU, memory and disk yet. Run "php artisan pulse:check".'));
        }

        $reportedAt = Carbon::createFromTimestamp((int) $last);
        $facts = [
            trans_choice(':count server|:count servers', $servers),
            __('Last report :time', ['time' => $reportedAt->diffForHumans()]),
        ];

        if ($reportedAt->lt(now()->subSeconds(self::SERVER_STATS_LATE_SECONDS))) {
            return new ServiceStatusData(ServiceState::Warning, __('Pulse records, but the server stats stopped. Is "pulse:check" running?'), $facts);
        }

        return new ServiceStatusData(ServiceState::Running, __('Pulse records requests, jobs and server stats.'), $facts);
    }

    /** Writes a small file to each disk, reads it back and deletes it. */
    private function storage(): ServiceStatusData
    {
        $facts = [];

        foreach (['private', 'public'] as $disk) {
            $config = (array) config("filesystems.disks.{$disk}");
            $facts[] = ucfirst($disk).': '.(($config['driver'] ?? '') === 's3' ? 'S3 · '.($config['bucket'] ?? '') : __('this server'));

            $path = 'status-check/'.Str::uuid7().'.txt';
            $files = Storage::disk($disk);

            try {
                $saved = $files->put($path, 'ok') && $files->get($path) === 'ok';
            } finally {
                $files->delete($path);
            }

            if (! $saved) {
                return new ServiceStatusData(ServiceState::Down, __('The :disk disk could not save a file and read it back.', ['disk' => $disk]), $facts);
            }
        }

        return new ServiceStatusData(ServiceState::Running, __('Files are saved and read back.'), $facts);
    }

    /** The mailer in use; an SMTP server is asked whether it answers, without sending. */
    private function email(): ServiceStatusData
    {
        $mailer = (string) config('mail.default');
        $config = (array) config("mail.mailers.{$mailer}");
        $transport = (string) ($config['transport'] ?? $mailer);

        if (in_array($transport, ['log', 'array'], true)) {
            return new ServiceStatusData(ServiceState::Warning, __('Emails are written to the log, not sent. Set up email in Settings → Email.'), [Str::headline($transport)]);
        }

        if ($transport !== 'smtp') {
            return new ServiceStatusData(ServiceState::Running, __('Sends with :service. It is not tested here, as that would send an email.', ['service' => Str::headline($transport)]), [Str::headline($transport)]);
        }

        $address = ($config['host'] ?? '').':'.($config['port'] ?? 587);
        $socket = @stream_socket_client('tcp://'.$address, $code, $message, self::TIMEOUT_SECONDS);

        if ($socket === false) {
            return new ServiceStatusData(ServiceState::Down, __('The mail server :address does not answer.', ['address' => $address]), ['SMTP']);
        }

        fclose($socket);

        return new ServiceStatusData(ServiceState::Running, __('The mail server :address answers.', ['address' => $address]), ['SMTP']);
    }

    /** The programs of Settings → Platform → Programs, at the paths in use. */
    private function programs(): ServiceStatusData
    {
        $keys = PlatformSettings::configKeys();

        $missing = array_values(array_filter(
            ServerProgram::cases(),
            fn (ServerProgram $program): bool => $this->programs->find((string) config($keys[$program->property()][0]->key)) === null,
        ));

        if ($missing !== []) {
            return new ServiceStatusData(
                ServiceState::Warning,
                __('Not found: :list. Some file types cannot be read.', ['list' => implode(', ', array_map(fn (ServerProgram $program): string => $program->value, $missing))]),
                [__(':found of :total found', ['found' => count(ServerProgram::cases()) - count($missing), 'total' => count(ServerProgram::cases())])],
            );
        }

        return new ServiceStatusData(ServiceState::Running, __('Every program is found.'), [
            __(':found of :total found', ['found' => count(ServerProgram::cases()), 'total' => count(ServerProgram::cases())]),
        ]);
    }

    private function millisecondsSince(int|float $started): int
    {
        return max(1, (int) round((hrtime(true) - $started) / 1e6));
    }
}
