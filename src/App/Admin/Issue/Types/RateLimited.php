<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Domain\Llm\Enums\LlmFailure;
use Support\Radar\IssueTypeKey;

/** The service limits how often the account may call it. */
final class RateLimited extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::RATE_LIMITED;
    }

    public function label(): string
    {
        return 'Too many requests';
    }

    public function title(IssueSubject $subject): string
    {
        return "{$subject->service->label} is getting too many requests";
    }

    public function advice(IssueSubject $subject): string
    {
        $name = $subject->service->label;

        return "{$name} limits how many requests the account sends. It usually passes; if it keeps happening, raise the limit at {$name} or send less traffic to it.";
    }

    public function failure(): LlmFailure
    {
        return LlmFailure::RateLimited;
    }
}
