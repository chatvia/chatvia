<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use App\Http\Requests\Agent\Source\File\ListSourceFilesRequest;
use Domain\Agent\Data\Resources\Sources\SourceFileDataResource;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Support\MimeType;

/**
 * Lists the agent's files for the Files, Audio and Video pages. Agent access
 * and the `agent.sources.train.files` permission are checked by the route
 * middleware.
 */
class AgentSourceFileController
{
    public function __invoke(ListSourceFilesRequest $request, Agent $agent): Collection
    {
        $files = $this->filesOfType($agent, $request->fileType())
            ->withCount(['agents', 'chunks'])
            ->get();

        return SourceFileDataResource::fromFiles($files);
    }

    /**
     * Without a type: every document format the parser accepts (PDF, office,
     * text, Markdown, ebooks, images), i.e. everything except audio and video,
     * which have their own pages.
     *
     * @return BelongsToMany<SourceFile, Agent>
     */
    private function filesOfType(Agent $agent, ?string $fileType): BelongsToMany
    {
        return match ($fileType) {
            'pdf' => $agent->pdfFiles(),
            'word' => $agent->wordFiles(),
            'audio' => $agent->audioFiles(),
            'video' => $agent->videoFiles(),
            default => $agent->files()->whereNotIn('mime_type', array_merge(MimeType::group('audio'), MimeType::group('video'))),
        };
    }
}
