<?php

namespace App\Http\Controllers\Settings\Security;

use Domain\Security\Data\SecurityAuditFiltersData;
use Domain\Security\Data\SecurityAuditRowData;
use Domain\Security\Enums\SecurityAuditCategory;
use Domain\Security\Enums\SecurityAuditOutcome;
use Domain\Security\Models\SecurityAuditLog;
use Domain\Security\Queries\SecurityAuditLogQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only viewer for the immutable `security_audit_logs` — every
 * authentication, membership, configuration, and data event for the workspace.
 * Part of the Security & Compliance suite. Gated by the `account.security`
 * team permission.
 */
class SecurityAuditController
{
    public function __invoke(Request $request, SecurityAuditLogQuery $audit): Response
    {
        $filters = new SecurityAuditFiltersData(
            category: $request->filled('category') ? (string) $request->string('category') : null,
            outcome: $request->filled('outcome') ? (string) $request->string('outcome') : null,
        );

        $rows = $audit->forWorkspace($request->user()->workspace()->id, $filters)
            ->withQueryString()
            ->through(fn (SecurityAuditLog $row): SecurityAuditRowData => SecurityAuditRowData::fromModel($row));

        return Inertia::render('dashboard/workspace/pages/security/SecurityAudit', [
            'rows' => $rows,
            'categories' => array_map(
                fn (SecurityAuditCategory $c): array => ['value' => $c->value, 'label' => $c->label()],
                SecurityAuditCategory::cases(),
            ),
            'outcomes' => array_column(SecurityAuditOutcome::cases(), 'value'),
            'filters' => $filters,
        ]);
    }
}
