<?php

declare(strict_types=1);

namespace App\Admin\Issue\Actions;

use App\Admin\Issue\Data\ReportedIssueData;
use App\Admin\Issue\Enums\IssueStatus;
use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Models\IssueOccurrence;
use App\Admin\Issue\Services\IssueReporter;
use App\Admin\Issue\Services\IssueTypes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves one raised issue: its issue (one per service, kind and, for a
 * per-subject kind, subject) is made or found, counted and marked seen, and the
 * occurrence is added with who was behind it, where it happened and what
 * the service said. A resolved issue that happens again opens again; an
 * ignored one stays ignored. The service is then marked as having an open
 * issue, so its next success closes it.
 *
 * Inserting the issue and counting it are one statement (an upsert), so
 * two workers saving the same issue at once never make two of it.
 */
class RecordIssueAction
{
    public function __construct(private readonly IssueTypes $types) {}

    /**
     * Past this many occurrences of one issue in a minute, the rest are
     * only counted, so a burst of failing calls cannot fill the database.
     */
    public const int MAX_OCCURRENCES_PER_MINUTE = 100;

    public function execute(ReportedIssueData $data): Issue
    {
        $reported = $data->issue;
        $type = $this->types->for($reported->type);
        $subject = $type?->perSubject() === true ? ($reported->subject ?? $reported->model) : null;
        $fingerprint = Issue::fingerprint($reported->service, $reported->type, $subject);

        $issue = DB::transaction(function () use ($data, $fingerprint, $subject): Issue {
            $this->upsert($data, $fingerprint, $subject);

            $issue = Issue::query()->where('fingerprint', $fingerprint)->firstOrFail();

            if ($this->flooded($issue, $data)) {
                return $issue;
            }

            IssueOccurrence::query()->create([
                'issue_id' => $issue->id,
                'workspace_id' => $data->workspaceId,
                'agent_id' => $data->workspaceId !== null ? $data->agentId : null,
                'actor_kind' => $data->actor->kind,
                'user_id' => $data->actor->userId,
                'chatter_id' => $data->actor->chatterId,
                'api_key_id' => $data->actor->apiKeyId,
                'model' => $data->issue->model,
                'http_status' => $data->issue->httpStatus,
                'endpoint' => $data->issue->endpoint,
                'message' => $data->issue->message,
                'origin_kind' => $data->originKind,
                'origin_name' => $data->originName,
                'occurred_at' => $data->occurredAt,
            ]);

            return $issue;
        });

        if ($issue->status === IssueStatus::Open && $type?->closesByItself() !== false) {
            Cache::forever(IssueReporter::openKey($issue->service, $issue->subject), true);
        }

        return $issue;
    }

    private function upsert(ReportedIssueData $data, string $fingerprint, ?string $subject): void
    {
        $resolved = IssueStatus::Resolved->value;
        $reported = $data->issue;

        Issue::query()->toBase()->upsert([[
            'external_id' => (string) Str::uuid7(),
            'fingerprint' => $fingerprint,
            'service' => $reported->service,
            'kind' => $reported->type,
            'subject' => $subject,
            'status' => IssueStatus::Open->value,
            'message' => $reported->message,
            'occurrences_count' => 1,
            'first_seen_at' => $data->occurredAt,
            'last_seen_at' => $data->occurredAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['fingerprint'], [
            'message' => DB::raw('COALESCE(excluded.message, issues.message)'),
            'occurrences_count' => DB::raw('issues.occurrences_count + 1'),
            'last_seen_at' => DB::raw('GREATEST(issues.last_seen_at, excluded.last_seen_at)'),
            'status' => DB::raw("CASE WHEN issues.status = '{$resolved}' THEN excluded.status ELSE issues.status END"),
            'resolution' => DB::raw("CASE WHEN issues.status = '{$resolved}' THEN NULL ELSE issues.resolution END"),
            'resolved_at' => DB::raw("CASE WHEN issues.status = '{$resolved}' THEN NULL ELSE issues.resolved_at END"),
            'updated_at',
        ]);
    }

    private function flooded(Issue $issue, ReportedIssueData $data): bool
    {
        return $issue->occurrences()
            ->where('occurred_at', '>', $data->occurredAt->subMinute())
            ->count() >= self::MAX_OCCURRENCES_PER_MINUTE;
    }
}
