<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Domain\Llm\Enums\LlmFailure;
use Support\Radar\IssueTypeKey;

/** The account at the service has no credit or quota left. */
final class OutOfCredit extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::OUT_OF_CREDIT;
    }

    public function label(): string
    {
        return 'Out of credit';
    }

    public function title(IssueSubject $subject): string
    {
        return "{$subject->service->label} is out of credit";
    }

    public function advice(IssueSubject $subject): string
    {
        $name = $subject->service->label;

        return "Add credit or change the plan at {$name}. The issue closes by itself once {$name} answers again.";
    }

    public function failure(): LlmFailure
    {
        return LlmFailure::OutOfCredit;
    }
}
