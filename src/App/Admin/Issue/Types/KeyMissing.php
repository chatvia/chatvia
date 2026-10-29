<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueSubject;
use Support\Radar\IssueTypeKey;

/** A model in use belongs to a provider that has no API key saved. */
final class KeyMissing extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::KEY_MISSING;
    }

    public function label(): string
    {
        return 'No key';
    }

    public function title(IssueSubject $subject): string
    {
        return "{$subject->service->label} has no API key";
    }

    public function advice(IssueSubject $subject): string
    {
        $name = $subject->service->label;

        return "A model in use belongs to {$name}, but no {$name} API key is saved. Add it in Settings → AI providers, or move the agents and app tasks to a model of another provider.";
    }

    public function actions(IssueSubject $subject): array
    {
        return [...parent::actions($subject), ...$this->keysAction($subject)];
    }
}
