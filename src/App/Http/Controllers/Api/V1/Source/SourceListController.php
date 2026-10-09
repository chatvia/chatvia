<?php

namespace App\Http\Controllers\Api\V1\Source;

use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\JsonResponse;

/**
 * Lists all knowledge sources attached to an agent, regardless of type.
 * Each entry includes ingestion status so customers can poll for completion.
 *
 * @tags Sources
 */
#[Group(name: 'Sources', weight: 70)]
class SourceListController
{
    /**
     * List sources
     */
    public function __invoke(Agent $agent): JsonResponse
    {
        $files = $agent->files()
            ->select(['source_files.id', 'filename', 'filetype', 'mime_type', 'size_in_kb', 'status', 'source_files.created_at'])
            ->get()
            ->map(fn (SourceFile $f): array => [
                'id' => $f->id,
                'kind' => 'file',
                'name' => $f->filename,
                'mime_type' => $f->mime_type,
                'size_kb' => $f->size_in_kb,
                'status' => $f->status->value,
                'created_at' => $f->created_at?->toIso8601String(),
            ]);

        $texts = $agent->texts()
            ->select(['source_texts.id', 'excerpt', 'length', 'status', 'source_texts.created_at'])
            ->get()
            ->map(fn (SourceText $t): array => [
                'id' => $t->id,
                'kind' => 'text',
                'name' => str($t->excerpt)->limit(60)->toString(),
                'length_pages' => $t->length,
                'status' => $t->status->value,
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        $urls = $agent->websites()
            ->select(['source_websites.id', 'url', 'status', 'source_websites.created_at'])
            ->get()
            ->map(fn (SourceWebsite $w): array => [
                'id' => $w->id,
                'kind' => 'url',
                'name' => $w->url,
                'status' => $w->status->value,
                'created_at' => $w->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $files->concat($texts)->concat($urls)->sortByDesc('created_at')->values()->all(),
        ]);
    }
}
