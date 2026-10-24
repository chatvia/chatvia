<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Services;

use App\Identity\Workspace\Models\Workspace;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Support\Str;
use LogicException;

/**
 * Builds the storage folder of a knowledge source:
 * `workspaces/{workspace}/sources/{morph alias}/{source}`. Every file of the
 * source lives there (the upload, the parser output or transcript, page
 * images, the text or website document, short-lived copies under `tmp/`).
 *
 * A source belongs to the workspace and can be used by several agents, so its
 * files are not under any agent: deleting an agent never removes a file another
 * agent still uses, deleting the source is one folder wipe, and deleting the
 * workspace removes them all with its own prefix. External ids are used so no
 * internal id shows up in a storage path.
 *
 * The source does not have to be saved yet: an upload writes its file before
 * the row exists, so it builds the path from an unsaved model that already has
 * its `workspace_id` and `external_id`.
 */
final class KnowledgeStoragePath
{
    /**
     * Workspace external ids by workspace id. The external id never changes,
     * so one query per workspace is enough for the whole process.
     *
     * @var array<int, string>
     */
    private static array $workspaceExternalIds = [];

    /**
     * @throws LogicException when the source has no workspace or external id yet
     */
    public static function forSource(SourceFile|SourceText|SourceWebsite|SourceYoutubeVideo $source, string $subfolder = ''): string
    {
        $externalId = (string) $source->getAttribute('external_id');
        $workspaceId = (int) $source->getAttribute('workspace_id');

        // An empty segment would point at the folder of every source of the
        // type (or of the workspace), and the delete observers wipe this path.
        if ($externalId === '' || $workspaceId === 0) {
            throw new LogicException('A knowledge source needs a workspace and an external id before it has a storage folder.');
        }

        $path = 'workspaces/'.self::workspaceExternalId($workspaceId).'/sources/'.$source->getMorphClass().'/'.$externalId;

        return $subfolder === '' ? $path : $path.'/'.Str::trim($subfolder, '/');
    }

    private static function workspaceExternalId(int $workspaceId): string
    {
        if (isset(self::$workspaceExternalIds[$workspaceId])) {
            return self::$workspaceExternalIds[$workspaceId];
        }

        $externalId = (string) Workspace::query()->whereKey($workspaceId)->value('external_id');

        if ($externalId === '') {
            throw new LogicException('The workspace of the knowledge source does not exist.');
        }

        return self::$workspaceExternalIds[$workspaceId] = $externalId;
    }
}
