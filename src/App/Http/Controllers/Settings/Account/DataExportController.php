<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Account\RequestDataExportRequest;
use App\Identity\Workspace\Actions\DataExport\RequestDataExportAction;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Exceptions\ActiveDataExportExistsException;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DataExportController extends Controller
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

        return Inertia::render('settings/DataExport', [
            'activeExport' => $activeExport ? [
                'id' => $activeExport->id,
                'status' => $activeExport->status->value,
                'file_size' => $activeExport->file_size,
                'expires_at' => $activeExport->expires_at?->toIso8601String(),
                'error_message' => $activeExport->error_message,
                'created_at' => $activeExport->created_at?->toIso8601String(),
                'download_url' => $activeExport->isReady() && ! $activeExport->isExpired()
                    ? route('settings.data-export.download', $activeExport)
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
                'data_export' => 'You already have a data export being prepared or ready to download.',
            ]);
        }

        return back()->with('status', 'data-export-requested');
    }

    public function download(DataExport $export): StreamedResponse
    {
        if (Gate::denies('download', $export)) {
            throw new AccessDeniedHttpException;
        }

        $filename = 'chatvia-export-'.$export->user_id.'-'.$export->created_at->format('Ymd-His').'.zip';

        return Storage::disk('local')->download($export->file_path, $filename);
    }

    public function destroy(DataExport $export): RedirectResponse
    {
        if (Gate::denies('delete', $export)) {
            throw new AccessDeniedHttpException;
        }

        if ($export->file_path) {
            Storage::disk('local')->delete($export->file_path);
        }

        $export->delete();

        return back()->with('status', 'data-export-deleted');
    }
}
