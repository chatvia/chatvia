<?php

declare(strict_types=1);

namespace App\Admin\Issue\Types;

use App\Admin\Issue\Data\IssueActionData;
use App\Admin\Issue\Data\IssueSubject;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use App\Identity\Workspace\Models\Workspace;

/**
 * A limit an admin set on one workspace, reached: the subject is the
 * workspace's id, shown by its name, with a button to its admin page,
 * where the limit is raised.
 */
abstract class WorkspaceLimitType extends IssueType
{
    public function subjectLabel(string $subject): string
    {
        return $this->workspace($subject)?->name ?? 'A deleted workspace';
    }

    public function actions(IssueSubject $subject): array
    {
        $workspace = $this->workspace($subject->subject ?? '');

        return $workspace instanceof Workspace
            ? [new IssueActionData('Open the workspace', WorkspaceResource::getUrl('view', ['record' => $workspace]))]
            : [];
    }

    public function perSubject(): bool
    {
        return true;
    }

    private function workspace(string $id): ?Workspace
    {
        return is_numeric($id) ? Workspace::query()->find((int) $id) : null;
    }
}
