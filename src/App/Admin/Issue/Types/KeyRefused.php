<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Domain\Llm\Enums\LlmFailure;
use Support\Radar\IssueTypeKey;

/** The service turned the API key away: it is wrong, deleted, or may not use the model. */
final class KeyRefused extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::KEY_REFUSED;
    }

    public function label(): string
    {
        return 'Key refused';
    }

    public function title(IssueSubject $subject): string
    {
        return "{$subject->service->label} refused the API key";
    }

    public function advice(IssueSubject $subject): string
    {
        $name = $subject->service->label;

        return "Check the {$name} API key in Settings, and that the account may use the model. Make a new key at {$name} if the old one was deleted.";
    }

    public function actions(IssueSubject $subject): array
    {
        return [...parent::actions($subject), ...$this->keysAction($subject)];
    }

    public function failure(): LlmFailure
    {
        return LlmFailure::KeyRefused;
    }
}
