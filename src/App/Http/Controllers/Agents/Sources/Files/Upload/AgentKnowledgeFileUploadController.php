<?php

namespace App\Http\Controllers\Agents\Sources\Files\Upload;

use Domain\Agents\Actions\Sources\AgentSourceAnalyzeFileAction;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeFileUploadController
{
    public function __invoke(Request $request, Agent $agent, AgentSourceAnalyzeFileAction $action): array
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(
            boolean: ! has_permission_to_agent($agent, 'agent.sources.train.files'),
            code: 403,
        );

        $uploadedFile = $request->file('file');

        abort_if(
            boolean: ! is_pdf_file($uploadedFile) && ! is_word_file($uploadedFile) && ! is_audio_file($uploadedFile) && ! is_video_file($uploadedFile),
            code: 422,
            message: 'File upload failed'
        );

        $info = $action->execute($uploadedFile, $request->user(), $agent);

        $file = $agent->addFile($uploadedFile);

        return [
            'id' => $file->id,
            'length' => $info->length,
            'mime_type' => $uploadedFile->getMimeType(),
        ];
    }
}
