<?php

namespace App\Http\Controllers\Settings\Security;

use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Enums\SecurityAuditCategory;
use Domain\Security\Models\SecurityAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only viewer for the immutable `security_audit_log` — every
 * authentication, membership, configuration, and data event for the workspace.
 * Part of the Security & Compliance suite. Gated by the `account.security`
 * team permission.
 */
class SecurityAuditController
{
    public function __invoke(Request $request): Response
    {
        $query = SecurityAuditLog::query()
            ->forWorkspace($request->user()->workspace()->id)
            ->with('user:id,name,email,profile_image_path')
            ->orderByDesc('id');

        $this->applyFilters($query, $request);

        // Cursor pagination pairs with the (workspace_id, id) index — no COUNT/OFFSET over the unbounded log.
        $rows = $query->cursorPaginate(30)
            ->withQueryString()
            ->through(fn (SecurityAuditLog $row): array => $row->toAuditArray());

        return Inertia::render('workspace/security/SecurityAudit', [
            'rows' => $rows,
            'categories' => array_map(
                fn (SecurityAuditCategory $c): array => ['value' => $c->value, 'label' => $c->label()],
                SecurityAuditCategory::cases(),
            ),
            'outcomes' => ['success', 'failure', 'denied'],
            'filters' => [
                'category' => $request->filled('category') ? (string) $request->string('category') : null,
                'outcome' => $request->filled('outcome') ? (string) $request->string('outcome') : null,
            ],
        ]);
    }

    /**
     * @param  Builder<SecurityAuditLog>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('outcome')) {
            $query->where('outcome', (string) $request->string('outcome'));
        }

        if ($request->filled('category')) {
            $category = SecurityAuditCategory::tryFrom((string) $request->string('category'));

            if ($category !== null) {
                // The table stores `action`, not category — expand to its actions.
                $actions = array_values(array_map(
                    fn (SecurityAuditAction $a): string => $a->value,
                    array_filter(SecurityAuditAction::cases(), fn (SecurityAuditAction $a): bool => $a->category() === $category),
                ));

                $query->whereIn('action', $actions);
            }
        }
    }
}
