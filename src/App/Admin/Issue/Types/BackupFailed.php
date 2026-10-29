<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Support\Radar\IssueTypeKey;

/** A backup did not finish. */
final class BackupFailed extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::BACKUP_FAILED;
    }

    public function label(): string
    {
        return 'Backup failed';
    }

    public function title(IssueSubject $subject): string
    {
        return 'A backup failed';
    }

    public function advice(IssueSubject $subject): string
    {
        return 'Open Settings → Backups to see why it failed, fix the cause, then press "Back up now".';
    }
}
