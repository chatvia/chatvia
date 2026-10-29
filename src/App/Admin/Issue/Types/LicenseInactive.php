<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Support\Radar\IssueTypeKey;

/** chatvia.ai says the license key is no longer active, so it was removed. */
final class LicenseInactive extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::LICENSE_INACTIVE;
    }

    public function label(): string
    {
        return 'License off';
    }

    public function title(IssueSubject $subject): string
    {
        return 'The license key was turned off';
    }

    public function advice(IssueSubject $subject): string
    {
        return 'The license key is no longer active at chatvia.ai, so it was removed. Enter an active key on the License page.';
    }
}
