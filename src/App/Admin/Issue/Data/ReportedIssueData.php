<?php

declare(strict_types=1);

namespace App\Admin\Issue\Data;

use App\Admin\Issue\Enums\IssueOriginKind;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Support\Radar\IssueRaised;

/**
 * A raised issue with where it happened, ready for the queue: what the app
 * was doing, who was behind it, the workspace and agent, and when.
 */
class ReportedIssueData extends Data
{
    public function __construct(
        public IssueRaised $issue,
        public IssueOriginKind $originKind,
        public ?string $originName,
        public IssueActorData $actor,
        public ?int $workspaceId,
        public ?int $agentId,
        public CarbonImmutable $occurredAt,
    ) {}
}
