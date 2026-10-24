<?php

declare(strict_types=1);

namespace Domain\Security\Actions;

use Domain\Security\Data\SecurityActorData;
use Domain\Security\Data\SecurityAuditFiltersData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\SecurityAuditLog;
use Domain\Security\Queries\SecurityAuditLogQuery;
use Generator;
use Illuminate\Support\LazyCollection;

/**
 * The workspace's security audit log as CSV rows (a header, then the newest
 * row first), for the Enterprise audit-log export. The export is itself a
 * security event, so it shows in the log and reaches the SIEM.
 */
class ExportSecurityAuditLogAction
{
    /** @var list<string> */
    public const array HEADER = [
        'Time', 'Action', 'Description', 'Category', 'Outcome', 'User', 'User email',
        'Target type', 'Target id', 'Target', 'IP address', 'Browser', 'Details',
    ];

    public function __construct(
        private readonly SecurityAuditLogQuery $audit,
    ) {}

    /**
     * @return LazyCollection<int, array<int, string|int|null>>
     */
    public function execute(int $workspaceId, SecurityAuditFiltersData $filters, SecurityActorData $actor): LazyCollection
    {
        event(SecurityEventOccurred::for(
            action: SecurityAuditAction::AuditExported,
            workspaceId: $workspaceId,
            actor: $actor,
            metadata: ['log' => 'security', 'category' => $filters->category, 'outcome' => $filters->outcome],
        ));

        $rows = $this->audit->allForWorkspace($workspaceId, $filters);

        return LazyCollection::make(
            /** @return Generator<int, array<int, string|int|null>> */
            function () use ($rows): Generator {
                yield self::HEADER;

                foreach ($rows as $row) {
                    yield $this->row($row);
                }
            },
        );
    }

    /**
     * @return array<int, string|int|null>
     */
    private function row(SecurityAuditLog $row): array
    {
        return [
            $row->created_at?->toIso8601String(),
            $row->action->value,
            $row->action->label(),
            $row->action->category()->value,
            $row->outcome->value,
            $row->user_name,
            $row->user_email,
            $row->target_type,
            $row->target_id,
            $row->target_label,
            $row->ip,
            $row->user_agent,
            $row->metadata === null ? $row->metadata_preview : json_encode($row->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }
}
