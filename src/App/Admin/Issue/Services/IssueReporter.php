<?php

declare(strict_types=1);

namespace App\Admin\Issue\Services;

use App\Admin\Issue\Actions\RecordIssueAction;
use App\Admin\Issue\Actions\ResolveRecoveredIssuesAction;
use App\Admin\Issue\Data\ReportedIssueData;
use App\Admin\Issue\Enums\IssueOriginKind;
use App\Admin\Issue\Jobs\RecordIssueJob;
use App\Admin\Issue\Jobs\ResolveRecoveredIssuesJob;
use App\Admin\Issue\Types\IssueType;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Support\Radar\IssueRaised;

/**
 * Where a raised issue ({@see IssueRaised}) and the news that a service
 * works again come in. An issue of a kind Radar knows gets what was going
 * on around it (who,
 * the workspace and agent, what the app was doing) and goes on the
 * `records` queue; a service that works again closes its open issues, but
 * only when it has one, which one cache read tells.
 *
 * Most issues are saved on the `records` queue; the ones that may come
 * from a broken queue (a failed job, the health check) are saved at once
 * ({@see self::reportNow()}). Nothing here may break the caller: a failure
 * is swallowed, and work done while an issue is being saved is not
 * reported again, so a broken queue or database never loops.
 */
final class IssueReporter
{
    /** The hidden Context key of the agent the request or job works for. */
    public const string AGENT_CONTEXT = 'agent_id';

    private static bool $reporting = false;

    public function __construct(
        private readonly IssueOrigin $origin,
        private readonly IssueActor $actor,
        private readonly Request $request,
        private readonly IssueTypes $types,
    ) {}

    /** The cache key that says a service (or one subject of it) has open issues that close by themselves. */
    public static function openKey(string $service, ?string $subject = null): string
    {
        return $subject === null
            ? "issues:open:{$service}"
            : Str::of($subject)->hash('sha1')->prepend("issues:open:{$service}:")->toString();
    }

    /** Saves the issue on the `records` queue. */
    public function report(IssueRaised $issue): void
    {
        // A statement, not a returned value: the job is sent when the
        // pending dispatch is let go, which must happen inside rescue().
        $this->guarded($issue, function (ReportedIssueData $reported): void {
            RecordIssueJob::dispatch($reported)->beforeCommit();
        });
    }

    /** Saves the issue at once, for one that may come from a broken queue. */
    public function reportNow(IssueRaised $issue): void
    {
        $this->guarded($issue, function (ReportedIssueData $reported): void {
            app(RecordIssueAction::class)->execute($reported);
        });
    }

    /** Closes the open issues of a service (or one subject of it) on the `records` queue, when it has any. */
    public function recovered(string $service, ?string $subject = null, ?string $type = null): void
    {
        if ($this->mayReport()) {
            rescue(function () use ($service, $subject, $type): void {
                if (Cache::has(self::openKey($service, $subject))) {
                    ResolveRecoveredIssuesJob::dispatch($service, $subject, $type);
                }
            }, report: false);
        }
    }

    /** Closes them at once, for the health check, which runs while the queue may be down. */
    public function recoveredNow(string $service, ?string $subject = null, ?string $type = null): void
    {
        if ($this->mayReport()) {
            rescue(fn (): int => app(ResolveRecoveredIssuesAction::class)->execute($service, $subject, $type), report: false);
        }
    }

    /**
     * Runs $save with the issue and what went on around it, unless Radar is
     * off, does not know the kind, or is saving an issue already.
     *
     * @param  callable(ReportedIssueData): void  $save
     */
    private function guarded(IssueRaised $issue, callable $save): void
    {
        if (! $this->mayReport()) {
            return;
        }

        if (! $this->types->for($issue->type) instanceof IssueType) {
            Log::debug('Radar does not know this kind of issue', ['type' => $issue->type]);

            return;
        }

        self::$reporting = true;

        try {
            rescue(function () use ($issue, $save): void {
                $save($this->reported($issue));
            }, report: false);
        } finally {
            self::$reporting = false;
        }
    }

    private function mayReport(): bool
    {
        return ! self::$reporting
            && IssueRecording::isOn()
            && ! collect([RecordIssueJob::class, ResolveRecoveredIssuesJob::class])->contains(IssueOrigin::runningJob());
    }

    private function reported(IssueRaised $issue): ReportedIssueData
    {
        $originKind = $this->origin->kind();
        $agent = $originKind === IssueOriginKind::Request ? $this->request->route('agent') : null;
        $agentId = $agent instanceof Agent ? $agent->id : Context::getHidden(self::AGENT_CONTEXT);
        $workspaceId = Context::getHidden('workspace_id') ?? ($agent instanceof Agent ? $agent->workspace_id : null);

        return new ReportedIssueData(
            issue: $issue,
            originKind: $originKind,
            originName: $this->origin->name($originKind),
            actor: $this->actor->current($originKind),
            workspaceId: is_numeric($workspaceId) ? (int) $workspaceId : null,
            agentId: is_numeric($agentId) ? (int) $agentId : null,
            occurredAt: now()->toImmutable(),
        );
    }
}
