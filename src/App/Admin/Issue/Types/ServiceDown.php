<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Domain\Llm\Enums\LlmFailure;
use Support\Radar\IssueTypeKey;

/** The service answered with an error of its own (HTTP 5xx). */
final class ServiceDown extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::SERVICE_DOWN;
    }

    public function label(): string
    {
        return 'Service failing';
    }

    public function title(IssueSubject $subject): string
    {
        return "{$subject->service->label} is failing";
    }

    public function advice(IssueSubject $subject): string
    {
        $name = $subject->service->label;

        return "{$name} had trouble on its side. It usually passes. Check {$name}'s status page if it keeps happening.";
    }

    public function failure(): LlmFailure
    {
        return LlmFailure::ProviderError;
    }
}
