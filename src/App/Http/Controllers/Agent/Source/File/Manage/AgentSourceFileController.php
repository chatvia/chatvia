<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use App\Http\Requests\Agent\Source\File\ListSourceFilesRequest;
use Domain\Agent\Data\Resources\Sources\SourceFileDataResource;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Support\MimeType;

/**
 * Lists the agent's files for the Files, Audio and Video pages, newest first,
 * one page at a time: either the files waiting to be trained or the others
 * (training, trained or failed). Agent access and the
 * `agent.sources.train.files` permission are checked by the route middleware.
 */
class AgentSourceFileController
{
    private const int PER_PAGE = 50;

    /**
     * @return LengthAwarePaginator<int, SourceFileDataResource>
     */
    public function __invoke(ListSourceFilesRequest $request, Agent $agent): LengthAwarePaginator
    {
        $files = $this->filesOfType($agent, $request->fileType())
            ->getQuery()
            ->select('source_files.*')
            ->where('source_files.status', $request->waitingForTraining() ? '=' : '!=', SourceStatus::Uploaded)
            ->withCount('agents')
            ->orderByDesc('source_files.id')
            ->paginate(self::PER_PAGE);

        // Only a file that is training shows progress, which needs its chunk count.
        $files->getCollection()
            ->filter(fn (SourceFile $file): bool => $file->status === SourceStatus::Processing)
            ->loadCount('chunks');

        return $files->through(fn (SourceFile $file): SourceFileDataResource => SourceFileDataResource::fromFile($file));
    }

    /**
     * Without a type: every document format the parser accepts (PDF, office,
     * text, Markdown, ebooks, images), i.e. everything except audio and video,
     * which have their own pages.
     *
     * @return MorphToMany<SourceFile, Agent, AgentSource>
     */
    private function filesOfType(Agent $agent, ?string $fileType): MorphToMany
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
