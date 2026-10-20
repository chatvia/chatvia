<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Source\File\Upload;

use App\Http\Requests\Agent\Source\File\UploadKnowledgeFileRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\UploadKnowledgeSourceFileAction;

/**
 * Uploads one knowledge file for the agent and stores it in the "uploaded"
 * state. The request refuses a duplicate; the action refuses a type that is
 * off or that this server cannot read, and measures the file once. Agent
 * access and the `agent.sources.train.files` permission are checked by the
 * route middleware.
 */
class AgentKnowledgeFileUploadController
{
    /**
     * @return array{id: int, length: int, mime_type: string}
     */
    public function __invoke(
        UploadKnowledgeFileRequest $request,
        Agent $agent,
        UploadKnowledgeSourceFileAction $upload,
    ): array {
        $data = $request->toData();
        $file = $upload->execute($agent, $data);

        return [
            'id' => $file->id,
            'length' => $file->length,
            'mime_type' => $data->mimeType,
        ];
    }
}
