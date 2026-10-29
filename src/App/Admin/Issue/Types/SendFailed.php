<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Support\Radar\IssueTypeKey;

/** An email could not be sent. */
final class SendFailed extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::SEND_FAILED;
    }

    public function label(): string
    {
        return 'Cannot send';
    }

    public function title(IssueSubject $subject): string
    {
        return 'Emails cannot be sent';
    }

    public function advice(IssueSubject $subject): string
    {
        return 'Check the email settings: the server, the sign-in and the sender address. Send a test email from there.';
    }
}
