<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Domain\Llm\Enums\LlmFailure;
use Support\Radar\IssueTypeKey;

/** The server could not connect to the service. */
final class Unreachable extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::UNREACHABLE;
    }

    public function label(): string
    {
        return 'Out of reach';
    }

    public function title(IssueSubject $subject): string
    {
        return "{$subject->service->label} cannot be reached";
    }

    public function advice(IssueSubject $subject): string
    {
        return "The server could not connect to {$subject->service->label}. Check the server's internet connection and firewall, and the address in Settings if you changed it.";
    }

    public function failure(): LlmFailure
    {
        return LlmFailure::Unreachable;
    }
}
