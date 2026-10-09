<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Enums;

/**
 * Where a data export is in its life: being prepared, ready to download,
 * failed, or expired (file deleted).
 */
enum DataExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';
}
