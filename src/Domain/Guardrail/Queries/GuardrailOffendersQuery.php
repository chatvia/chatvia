<?php

declare(strict_types=1);

namespace Domain\Guardrail\Queries;

use App\Identity\Chatter\Models\Chatter;
use Domain\Guardrail\Data\GuardrailOffenderData;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardrailOffenderKind;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Models\GuardrailEvent;
use Domain\Security\Models\BlockedVisitor;
use Illuminate\Support\Collection;

/**
 * The person behind each abusive trigger on one page of the Guardrails
 * Activity log. A trigger counts when an abusive guard ({@see GuardType::abusive()})
 * fired on what the person wrote or what it made the agent say — never on
 * knowledge or tool results, which the person did not write. Four small
 * queries for the whole page: deactivated chatters, active visitor blocks,
 * and the 24-hour counts per chatter and per visitor.
 */
class GuardrailOffendersQuery
{
    /**
     * @param  Collection<int, GuardrailEvent>  $events
     * @return Collection<int, GuardrailOffenderData> keyed by event id; triggers no one can be blocked for are left out
     */
    public function forEvents(Collection $events, int $workspaceId): Collection
    {
        $blamable = $events->filter($this->isBlamable(...));

        if ($blamable->isEmpty()) {
            return collect();
        }

        $chatterIds = $blamable->pluck('chatter_id')->filter()->unique()->values();
        $visitorKeys = $blamable->pluck('visitor_key')->filter()->unique()->values();

        $chatters = $chatterIds->isEmpty() ? collect() : Chatter::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $chatterIds)
            ->get(['id', 'external_id', 'deactivated_at'])
            ->keyBy('id');

        $blockedKeys = $visitorKeys->isEmpty() ? collect() : BlockedVisitor::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('visitor_key', $visitorKeys)
            ->active()
            ->pluck('visitor_key')
            ->flip();

        $chatterAttempts = $this->recentAttempts($workspaceId, 'chatter_id', $chatterIds);
        $visitorAttempts = $this->recentAttempts($workspaceId, 'visitor_key', $visitorKeys);

        return $blamable->mapWithKeys(function (GuardrailEvent $event) use ($chatters, $blockedKeys, $chatterAttempts, $visitorAttempts): array {
            if ($event->chatter_id !== null) {
                $chatter = $chatters->get($event->chatter_id);

                // A chatter deleted since: nobody left to block.
                return $chatter instanceof Chatter ? [$event->id => new GuardrailOffenderData(
                    kind: GuardrailOffenderKind::Chatter,
                    blocked: $chatter->isDeactivated(),
                    recent_attempts: (int) ($chatterAttempts[$event->chatter_id] ?? 0),
                    chatter_external_id: $chatter->external_id,
                )] : [];
            }

            return [$event->id => new GuardrailOffenderData(
                kind: GuardrailOffenderKind::Visitor,
                blocked: $blockedKeys->has($event->visitor_key),
                recent_attempts: (int) ($visitorAttempts[$event->visitor_key] ?? 0),
                visitor_key: $event->visitor_key,
            )];
        });
    }

    /** Whether the trigger is an abusive one that a chatter or visitor caused. */
    public function isBlamable(GuardrailEvent $event): bool
    {
        return in_array($event->guard_type, GuardType::abusive(), true)
            && in_array($event->stage, [GuardStage::Input, GuardStage::Output], true)
            && in_array($event->outcome, [GuardOutcome::Blocked, GuardOutcome::Flagged], true)
            && ($event->chatter_id !== null || $event->visitor_key !== null);
    }

    /**
     * Abusive triggers in the last 24 hours, per chatter or per visitor.
     *
     * @param  'chatter_id'|'visitor_key'  $column
     * @param  Collection<int, int|string>  $values
     * @return Collection<int|string, int>
     */
    private function recentAttempts(int $workspaceId, string $column, Collection $values): Collection
    {
        if ($values->isEmpty()) {
            return collect();
        }

        return GuardrailEvent::query()
            ->forWorkspace($workspaceId)
            ->whereIn($column, $values)
            ->where('created_at', '>=', now()->subDay())
            ->whereIn('guard_type', GuardType::abusive())
            ->whereIn('stage', [GuardStage::Input, GuardStage::Output])
            ->whereIn('outcome', [GuardOutcome::Blocked, GuardOutcome::Flagged])
            ->selectRaw($column.', count(*) as attempts')
            ->groupBy($column)
            ->pluck('attempts', $column)
            ->map(fn (mixed $count): int => (int) $count);
    }
}
