<?php

declare(strict_types=1);

namespace Domain\Security\Queries;

use Domain\Security\Data\SecurityAuditFiltersData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Enums\SecurityAuditCategory;
use Domain\Security\Models\SecurityAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\LazyCollection;

/**
 * Filtered, newest-first page of the immutable `security_audit_logs` for the
 * Settings → Security viewer (and every matching row for its CSV export).
 * Cursor pagination pairs with the
 * (workspace_id, id) index — no COUNT(*)/OFFSET over the unbounded log.
 */
class SecurityAuditLogQuery
{
    /**
     * @return CursorPaginator<int, SecurityAuditLog>
     */
    public function forWorkspace(int $workspaceId, SecurityAuditFiltersData $filters, int $perPage = 30): CursorPaginator
    {
        return $this->filtered($workspaceId, $filters)
            ->with('user:id,name,email,profile_image_path')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    /**
     * Every matching row, newest first, read in chunks for the CSV export.
     *
     * @return LazyCollection<int, SecurityAuditLog>
     */
    public function allForWorkspace(int $workspaceId, SecurityAuditFiltersData $filters): LazyCollection
    {
        return $this->filtered($workspaceId, $filters)->lazyByIdDesc(500);
    }

    /**
     * @return Builder<SecurityAuditLog>
     */
    private function filtered(int $workspaceId, SecurityAuditFiltersData $filters): Builder
    {
        return SecurityAuditLog::query()
            ->forWorkspace($workspaceId)
            ->when($filters->outcome !== null, fn (Builder $query) => $query->where('outcome', $filters->outcome))
            ->when($filters->category !== null, fn (Builder $query): Builder => $this->whereCategory($query, $filters->category ?? ''));
    }

    /**
     * The table stores `action`, not category — expand a category filter to
     * its actions. An unknown category filters nothing out.
     *
     * @param  Builder<SecurityAuditLog>  $query
     * @return Builder<SecurityAuditLog>
     */
    private function whereCategory(Builder $query, string $category): Builder
    {
        $category = SecurityAuditCategory::tryFrom($category);

        if ($category === null) {
            return $query;
        }

        $actions = array_values(array_map(
            fn (SecurityAuditAction $action): string => $action->value,
            array_filter(SecurityAuditAction::cases(), fn (SecurityAuditAction $action): bool => $action->category() === $category),
        ));

        return $query->whereIn('action', $actions);
    }
}
