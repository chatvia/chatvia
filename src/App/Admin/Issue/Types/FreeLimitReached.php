<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueActionData;
use App\Admin\Issue\Data\IssueSubject;
use App\Admin\Pages\License;
use Support\Radar\IssueTypeKey;

/**
 * A free-use limit turned someone away: without a license key the install
 * holds one workspace, and a new one was refused. It closes when an active
 * license key is saved.
 */
final class FreeLimitReached extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::FREE_LIMIT_REACHED;
    }

    public function label(): string
    {
        return 'Free limit reached';
    }

    public function title(IssueSubject $subject): string
    {
        return 'A new workspace was refused: the free limit is one';
    }

    public function advice(IssueSubject $subject): string
    {
        return 'Without a license key, this install holds one workspace, so sign-up is closed and new workspaces are refused. Add a license key on the License page to allow more.';
    }

    public function actions(IssueSubject $subject): array
    {
        return [new IssueActionData('Open License', License::getUrl())];
    }
}
