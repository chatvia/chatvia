<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Source\File\Upload;

use App\Http\Requests\Agent\Source\File\UploadKnowledgeArchiveRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\UploadKnowledgeArchiveAction;
use Domain\Knowledge\Source\File\Data\ArchiveUploadResultData;

/**
 * Uploads a zip archive of knowledge files for the agent: every supported
 * file inside it, folders and nested archives included, is stored in the
 * "uploaded" state as its own file; the rest are named in the response.
 * Agent access and the `agent.sources.train.files` permission are checked by
 * the route middleware.
 */
class AgentKnowledgeArchiveUploadController
{
    public function __invoke(
        UploadKnowledgeArchiveRequest $request,
        Agent $agent,
        UploadKnowledgeArchiveAction $upload,
    ): ArchiveUploadResultData {
        return $upload->execute($agent, $request->toData());
    }
}
