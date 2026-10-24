<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Tools;

use Domain\Conversation\Data\Audit\ToolCallAuditFiltersData;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Queries\ToolCallAuditQuery;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Generator;
use Illuminate\Support\LazyCollection;

/**
 * The workspace's tool-call audit log (every tool call its agents made) as
 * CSV rows, a header then the newest call first, for the Enterprise
 * audit-log export. Tool results are left out: they can be long and hold
 * customer data. The export is recorded as a security event.
 */
class ExportToolCallAuditAction
{
    /** @var list<string> */
    public const array HEADER = [
        'Time', 'Agent', 'User', 'Tool', 'Tool type', 'Connector', 'Outcome', 'Error',
        'Needed approval', 'Decided at', 'Decision note', 'Latency (ms)', 'Trace id', 'Conversation id', 'Arguments',
    ];

    public function __construct(
        private readonly ToolCallAuditQuery $audit,
    ) {}

    /**
     * @return LazyCollection<int, array<int, string|int|bool|null>>
     */
    public function execute(int $workspaceId, ToolCallAuditFiltersData $filters, SecurityActorData $actor): LazyCollection
    {
        event(SecurityEventOccurred::for(
            action: SecurityAuditAction::AuditExported,
            workspaceId: $workspaceId,
            actor: $actor,
            metadata: ['log' => 'tool_calls', 'outcome' => $filters->outcome, 'connector' => $filters->connector, 'agent_id' => $filters->agent_id],
        ));

        $calls = $this->audit->allForWorkspace($workspaceId, $filters);

        return LazyCollection::make(
            /** @return Generator<int, array<int, string|int|bool|null>> */
            function () use ($calls): Generator {
                yield self::HEADER;

                foreach ($calls as $call) {
                    yield $this->row($call);
                }
            },
        );
    }

    /**
     * @return array<int, string|int|bool|null>
     */
    private function row(AgentActivity $call): array
    {
        return [
            $call->created_at?->toIso8601String(),
            $call->agent_name,
            $call->user_name,
            $call->tool_name,
            $call->tool_kind->value,
            $call->connector_name ?? $call->connector_slug,
            $call->outcome->value,
            $call->is_error,
            $call->is_gated,
            $call->decided_at?->toIso8601String(),
            $call->decision_note,
            $call->latency_ms,
            $call->trace_id,
            $call->conversation_external_id,
            $call->arguments === null ? $call->arguments_preview : json_encode($call->arguments, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }
}
