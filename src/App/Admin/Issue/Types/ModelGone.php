<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Issue\Data\IssueActionData;
use App\Admin\Issue\Data\IssueSubject;
use Domain\Llm\Enums\LlmFailure;
use Support\Radar\IssueTypeKey;

/**
 * The provider no longer has a model in use. A working provider does not
 * bring it back, so it stays open until an admin picks another model.
 */
final class ModelGone extends IssueType
{
    public function key(): string
    {
        return IssueTypeKey::MODEL_GONE;
    }

    public function label(): string
    {
        return 'Model gone';
    }

    public function title(IssueSubject $subject): string
    {
        $name = $subject->service->label;

        return $subject->subject !== null ? "{$name} no longer has the model {$subject->subject}" : "{$name} no longer has a model in use";
    }

    public function advice(IssueSubject $subject): string
    {
        return "Pick another model for the agents and app tasks that use it, in Settings → AI models or in each agent's Setup → Model.";
    }

    public function actions(IssueSubject $subject): array
    {
        return [new IssueActionData('Open AI models settings', ManageAiModelSettings::getUrl())];
    }

    public function closesByItself(): bool
    {
        return false;
    }

    public function failure(): LlmFailure
    {
        return LlmFailure::ModelUnavailable;
    }

    public function perSubject(): bool
    {
        return true;
    }
}
