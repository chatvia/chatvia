<?php

namespace App\Http\Controllers\Agent\Source\File\Upload;

use App\Http\Requests\Agent\Source\File\UploadKnowledgeFileRequest;
use Domain\Agent\Actions\Sources\AgentSourceAnalyzeFileAction;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\UploadKnowledgeSourceFileAction;

/**
 * Uploads one knowledge file for the agent: checks the quota and duplicates,
 * then stores it in the "uploaded" state. Agent access and the
 * `agent.sources.train.files` permission are checked by the route middleware.
 */
class AgentKnowledgeFileUploadController
{
    /**
     * @return array{id: int, length: int, mime_type: string}
     */
    public function __invoke(
        UploadKnowledgeFileRequest $request,
        Agent $agent,
        AgentSourceAnalyzeFileAction $analyze,
        UploadKnowledgeSourceFileAction $upload,
    ): array {
        $data = $request->toData();

        abort_unless($data->isSupported(), 422, __('Unsupported file type.'));

        $info = $analyze->execute($request->file('file'), $request->user(), $agent);
        $file = $upload->execute($agent, $data);

        return [
            'id' => $file->id,
            'length' => $info->length,
            'mime_type' => $data->mimeType,
        ];
    }
}
