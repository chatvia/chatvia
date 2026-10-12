<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Requests\Settings\Account\RequestDataExportRequest;
use App\Identity\Workspace\Actions\DataExport\DeleteDataExportAction;
use App\Identity\Workspace\Actions\DataExport\RequestDataExportAction;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Exceptions\ActiveDataExportExistsException;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
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
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $activeExport = DataExport::query()
            ->where('user_id', $user->id)
            ->where(function ($query): void {
                $query->whereIn('status', [
                    DataExportStatus::Pending,
                    DataExportStatus::Processing,
                    DataExportStatus::Failed,
                ])->orWhere(function ($q): void {
                    $q->where('status', DataExportStatus::Ready)
                        ->where('expires_at', '>', now());
                });
            })
            ->latest('id')
            ->first();

        return Inertia::render('account/DataExport', [
            'activeExport' => $activeExport ? [
                'id' => $activeExport->id,
                'status' => $activeExport->status->value,
                'file_size' => $activeExport->file_size,
                'expires_at' => $activeExport->expires_at?->toIso8601String(),
                'error_message' => $activeExport->error_message,
                'created_at' => $activeExport->created_at?->toIso8601String(),
                'download_url' => $activeExport->isReady() && ! $activeExport->isExpired()
                    ? route('account.data-export.download', $activeExport)
                    : null,
            ] : null,
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
