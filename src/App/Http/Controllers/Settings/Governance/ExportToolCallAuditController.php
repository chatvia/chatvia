<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Governance;

use Domain\Conversation\Actions\Tools\ExportToolCallAuditAction;
use Domain\Conversation\Data\Audit\ToolCallAuditFiltersData;
use Domain\Security\Data\SecurityActorData;
use Illuminate\Http\Request;
use Support\Http\CsvDownload;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the workspace's tool-call audit log as a CSV file, with the same
 * filters as the Governance viewer. An Enterprise feature: the route needs
 * `license.enterprise` and the `account.governance` team permission.
 */
class ExportToolCallAuditController
{
    public function __invoke(Request $request, ExportToolCallAuditAction $export, CsvDownload $csv): StreamedResponse
    {
        $user = $request->user();

        $filters = new ToolCallAuditFiltersData(
            outcome: $request->filled('outcome') ? (string) $request->string('outcome') : null,
            connector: $request->filled('connector') ? (string) $request->string('connector') : null,
            agent_id: $request->filled('agent_id') ? $request->integer('agent_id') : null,
        );

        $rows = $export->execute(
            $user->workspace()->id,
            $filters,
            SecurityActorData::forUser($user, $request->ip(), $request->userAgent()),
        );

        return $csv->response($rows, 'tool-call-audit-log-'.now()->format('Ymd-His').'.csv');
    }
}
