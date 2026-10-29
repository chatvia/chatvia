<?php

declare(strict_types=1);

namespace App\Admin\Issue\Services;

use App\Admin\Issue\Enums\IssueOriginKind;
use App\Admin\Issue\Listeners\TrackIssueOrigin;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What the app was doing when an issue happened: a request ("POST
 * /chat/support"), a background job (its class) or an artisan command.
 * The job and the command are noted as they start, by
 * {@see TrackIssueOrigin}, because the log entry
 * itself does not say.
 */
final class IssueOrigin
{
    private static ?string $job = null;

    private static ?string $command = null;

    public function __construct(private readonly Request $request) {}

    public static function jobStarted(string $job): void
    {
        self::$job = $job;
    }

    public static function jobFinished(): void
    {
        self::$job = null;
    }

    public static function commandStarted(string $command): void
    {
        self::$command = $command;
    }

    /** The class of the job this worker is running, if any. */
    public static function runningJob(): ?string
    {
        return self::$job;
    }

    public function kind(): IssueOriginKind
    {
        return match (true) {
            self::$job !== null => IssueOriginKind::Job,
            $this->request->route() !== null, ! app()->runningInConsole() => IssueOriginKind::Request,
            default => IssueOriginKind::Command,
        };
    }

    /**
     * The job's class, the request's method and path (never its query
     * string, which may hold personal data or a token), or the command.
     */
    public function name(IssueOriginKind $kind): ?string
    {
        return match ($kind) {
            IssueOriginKind::Job => self::$job,
            IssueOriginKind::Request => Str::of($this->request->path())->start('/')->prepend("{$this->request->method()} ")->toString(),
            IssueOriginKind::Command => self::$command,
        };
    }
}
