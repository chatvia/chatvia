<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Security;

use Domain\Security\Actions\ExportSecurityAuditLogAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Data\SecurityAuditFiltersData;
use Illuminate\Http\Request;
use Support\Http\CsvDownload;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the workspace's security audit log as a CSV file, with the same
 * filters as the viewer. An Enterprise feature: the route needs
 * `license.enterprise` and the `account.security` team permission.
 */
class ExportSecurityAuditLogController
{
    public function __invoke(Request $request, ExportSecurityAuditLogAction $export, CsvDownload $csv): StreamedResponse
    {
        $user = $request->user();

        $filters = new SecurityAuditFiltersData(
            category: $request->filled('category') ? (string) $request->string('category') : null,
            outcome: $request->filled('outcome') ? (string) $request->string('outcome') : null,
        );

        $rows = $export->execute(
            $user->workspace()->id,
            $filters,
            SecurityActorData::forUser($user, $request->ip(), $request->userAgent()),
        );

        return $csv->response($rows, 'security-audit-log-'.now()->format('Ymd-His').'.csv');
    }
}
