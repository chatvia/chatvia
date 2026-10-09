<?php

namespace App\Identity\Workspace\Actions\DataExport;

use App\Identity\Workspace\Data\DataExport\RequestDataExportData;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Exceptions\ActiveDataExportExistsException;
use App\Identity\Workspace\Jobs\PrepareDataExportJob;
use App\Identity\Workspace\Models\DataExport;
use Illuminate\Support\Str;

/**
 * Starts a GDPR data export: records it and queues the job that builds the
 * ZIP. Refused while another export is still being prepared or can be
 * downloaded.
 */
class RequestDataExportAction
{
    public function execute(RequestDataExportData $data): DataExport
    {
        $userId = $data->user->id;

        $hasActive = DataExport::query()
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->whereIn('status', [
                    DataExportStatus::Pending,
                    DataExportStatus::Processing,
                ])->orWhere(function ($q): void {
                    $q->where('status', DataExportStatus::Ready)
                        ->where(function ($q2): void {
                            $q2->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                });
            })
            ->exists();

        if ($hasActive) {
            throw ActiveDataExportExistsException::forUser($userId);
        }

        $export = DataExport::create([
            'user_id' => $userId,
            'status' => DataExportStatus::Pending,
            'download_token' => Str::random(64),
        ]);

        PrepareDataExportJob::dispatch($export->id);

        return $export;
    }
}
