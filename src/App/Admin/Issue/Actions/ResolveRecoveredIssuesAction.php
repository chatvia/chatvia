<?php

declare(strict_types=1);

namespace App\Admin\Issue\Actions;

use App\Admin\Issue\Enums\IssueResolution;
use App\Admin\Issue\Enums\IssueStatus;
use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Services\IssueReporter;
use App\Admin\Issue\Services\IssueTypes;
use App\Admin\Issue\Types\IssueType;
use Illuminate\Support\Facades\Cache;

/**
 * A service, or one subject of it (a job class, a command, a workspace),
 * works again: its open issues of the kinds that close by themselves are
 * resolved as recovered, and it is no longer marked as having one. Without
 * a subject only the service's own issues close, never a subject's (a
 * running queue does not mend a failing job). With a type, only issues of
 * that kind close. Returns how many were closed.
 */
class ResolveRecoveredIssuesAction
{
    public function __construct(private readonly IssueTypes $types) {}

    public function execute(string $service, ?string $subject = null, ?string $type = null): int
    {
        $closed = Issue::query()
            ->where('service', $service)
            ->when($subject !== null, fn ($query) => $query->where('subject', $subject), fn ($query) => $query->whereNull('subject'))
            ->when($type !== null, fn ($query) => $query->where('kind', $type))
            ->where('status', IssueStatus::Open)
            ->whereIn('kind', $this->types->all()->filter(fn (IssueType $type): bool => $type->closesByItself())->keys()->all())
            ->update([
                'status' => IssueStatus::Resolved,
                'resolution' => IssueResolution::Recovered,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        // Other kinds of the same subject may still be open: the flag only
        // goes when none is.
        $stillOpen = Issue::query()
            ->where('service', $service)
            ->when($subject !== null, fn ($query) => $query->where('subject', $subject), fn ($query) => $query->whereNull('subject'))
            ->where('status', IssueStatus::Open)
            ->exists();

        if (! $stillOpen) {
            Cache::forget(IssueReporter::openKey($service, $subject));
        }

        return $closed;
    }
}
