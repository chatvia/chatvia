<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Requests\Settings\Account\RequestDataExportRequest;
use App\Identity\Workspace\Actions\DataExport\DeleteDataExportAction;
use App\Identity\Workspace\Actions\DataExport\RequestDataExportAction;
use App\Identity\Workspace\Data\DataExport\DataExportResourceData;
use App\Identity\Workspace\Exceptions\ActiveDataExportExistsException;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Queries\ActiveDataExportQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The GDPR data export page: shows the current export, starts a new one and
 * deletes an export ({@see DownloadDataExportController} serves the ZIP). Only
 * the owner of an export may delete it (see DataExportPolicy).
 */
class DataExportController
{
    public function edit(Request $request, ActiveDataExportQuery $active): Response
    {
        $export = $active->forUser($request->user());

        return Inertia::render('account/DataExport', [
            'activeExport' => $export !== null ? DataExportResourceData::fromModel($export) : null,
        ]);
    }

    public function store(
        RequestDataExportRequest $request,
        RequestDataExportAction $action,
    ): RedirectResponse {
        try {
            $action->execute($request->toData());
        } catch (ActiveDataExportExistsException) {
            return back()->withErrors([
                'data_export' => __('You already have a data export being prepared or ready to download.'),
            ]);
        }

        return back()->with('status', 'data-export-requested');
    }

    public function destroy(DataExport $export, DeleteDataExportAction $deleteExport): RedirectResponse
    {
        // Someone else's export is a 404; a not-ready one is a 403 (DataExportPolicy).
        Gate::authorize('delete', $export);

        $deleteExport->execute($export);

        return back()->with('status', 'data-export-deleted');
    }
}
