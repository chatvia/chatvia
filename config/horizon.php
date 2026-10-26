<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Notification Email
    |--------------------------------------------------------------------------
    |
    | Where Horizon sends its alert emails (for example, long queue waits).
    | Leave it empty to turn these emails off.
    |
    */

    'notify_email' => env('HORIZON_NOTIFY_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    // Only the queues with a pool of their own: Horizon measures a shared
    // pool (ingestion, media) under its joined queue names.
    'waits' => [
        'redis:messages' => 30,
        'redis:default' => 120,
        'redis:analytics' => 900,
        // Quick database writes: a backlog here means the dashboards lag.
        'redis:records' => 60,
        // A voice call waits for its worker while the caller listens to
        // "Connecting…": anything past a few seconds means the pool is full.
        'redis-long:voice' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        // Messaging-channel replies and storing messages (`messages`), then
        // e-mails, embeddings and other short jobs (`default`). A pool per
        // queue, so a reply never waits behind the rest. A reply may call the
        // model and the tools several times, hence the timeout (the `redis`
        // connection's retry_after outlasts it).
        'chat' => [
            'connection' => 'redis',
            'queue' => ['messages', 'default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 6,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 300,
            'nice' => 0,
        ],
        // Conversation analysis with the model, evals and SIEM delivery, with a
        // cap of their own so a backlog never takes workers from the chat.
        'analytics' => [
            'connection' => 'redis',
            'queue' => ['analytics'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 300,
            'nice' => 5,
        ],
        // Quick database writes: analytics counters, tool-call audits, MCP
        // call counts, guardrail and security audit rows. A pool of its own,
        // so they never wait behind the slow model calls on `analytics`.
        'records' => [
            'connection' => 'redis',
            'queue' => ['records'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 5,
        ],
        // Reading files and websites, then exports and deletions. One pool
        // takes the queues in this order (balance off), so a source that has
        // started finishes first, and an idle server keeps one worker, not
        // one per queue. Niced, so the web app stays quick while it works.
        'ingestion' => [
            'connection' => 'redis-long',
            'queue' => ['ingestion.chunks', 'ingestion.summary', 'ingestion.text', 'ingestion.pdf', 'ingestion.word', 'ingestion.website', 'exports'],
            'balance' => false,
            'minProcesses' => 1,
            'maxProcesses' => 4,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 3,
            'timeout' => 600,
            'nice' => 10,
        ],
        // Transcribing audio and video can take hours: `redis-media`'s
        // retry_after outlasts MediaIngestionJob's timeout. Backups an admin
        // starts by hand (RunBackupJob) run here too, for the same reason.
        'media' => [
            'connection' => 'redis-media',
            'queue' => ['ingestion.audio', 'ingestion.video', 'backups'],
            'balance' => false,
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 2,
            'timeout' => 86000,
            'nice' => 10,
        ],
        // Live voice calls: each worker joins one call (OpenAI's sideband
        // WebSocket) and holds it until the call ends, so maxProcesses is also
        // the most calls at once (VoiceCallSlots). `redis-long`'s retry_after
        // (1900 s) outlasts the longest call (25 minutes) plus its wrap-up; a
        // call is never retried. Not niced: the worker answers in real time.
        'voice' => [
            'connection' => 'redis-long',
            'queue' => ['voice'],
            'balance' => false,
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 1620,
            'nice' => 0,
        ],
    ],

    /*
    | Every environment runs the supervisors above. The most workers of each
    | (maxProcesses) fit a small server: Horizon starts a supervisor with half
    | of it, keeps at least minProcesses per pool, and each worker is a PHP
    | process of about 80 MB, so 18 workers at most, 7 when idle. The admin
    | panel changes them (Settings, Platform, Queue workers); Horizon reads
    | them when it starts.
    */
    'environments' => [
        '*' => [],
    ],
];
