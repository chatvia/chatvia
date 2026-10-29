<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueActionData;
use App\Admin\Issue\Data\IssueSubject;
use Domain\Llm\Enums\LlmFailure;
use Support\Radar\IssueRaised;

/**
 * A kind of issue Radar knows: its key (stored, raised with
 * {@see IssueRaised}), how it is named, what the admin should
 * do, the buttons that lead there, and whether it closes by itself once the
 * service works again. To add one, extend this class and list it in
 * config/radar.php.
 */
abstract class IssueType
{
    abstract public function key(): string;

    /** A short name for filters: "Key refused". */
    abstract public function label(): string;

    /** The issue's title: "fal.ai refused the API key". */
    abstract public function title(IssueSubject $subject): string;

    /** What the admin can do about it, in a sentence or two. */
    abstract public function advice(IssueSubject $subject): string;

    /**
     * The buttons that lead to the fix: by default the page where the
     * service is set up.
     *
     * @return list<IssueActionData>
     */
    public function actions(IssueSubject $subject): array
    {
        $url = $subject->service->settingsUrl;

        return $url !== null ? [new IssueActionData("Open {$subject->service->label} settings", $url)] : [];
    }

    /**
     * A button to the page where the service makes keys, when it has one.
     *
     * @return list<IssueActionData>
     */
    protected function keysAction(IssueSubject $subject): array
    {
        $keys = $subject->service->keysUrl;

        return $keys !== null ? [new IssueActionData("Keys at {$subject->service->label}", $keys, external: true)] : [];
    }

    /** Whether the issue closes by itself the next time its service works. */
    public function closesByItself(): bool
    {
        return true;
    }

    /** The refused outside call this kind stands for, if any, so Radar spots it by itself. */
    public function failure(): ?LlmFailure
    {
        return null;
    }

    /** A subject as people read it; by default as it is stored. */
    public function subjectLabel(string $subject): string
    {
        return $subject;
    }

    /**
     * Whether each subject (a model, a job class, a workspace…) is an issue
     * of its own; such an issue needs a subject.
     */
    public function perSubject(): bool
    {
        return false;
    }
}
