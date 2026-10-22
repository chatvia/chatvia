<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Services;

use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Data\SourceLabelData;
use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Support\MimeType;

/**
 * Names the source of each retrieved chunk ({@see SourceLabelData}), with a
 * few queries for the whole set:
 *
 *  - a file: its name, and the page for a PDF (`chunk_index` is the page);
 *  - a website chunk: the page it came from (its title, else its address),
 *    linked; chunks are shared by the site's pages, so the first page that
 *    links the chunk is used, else the site's own address;
 *  - a typed text: the start of the text;
 *  - a video: its title and link.
 *
 * Stored files are never linked: their storage is private.
 */
class SourceLabeler
{
    /** Longest text excerpt kept as a label. */
    private const int TEXT_LABEL_LENGTH = 80;

    /** Label of a chunk whose source is gone. */
    private const string UNKNOWN = 'Unknown source';

    /**
     * @param  list<RetrievedChunkData>  $chunks
     * @return array<int, SourceLabelData> keyed by chunk id
     */
    public function forChunks(array $chunks): array
    {
        $byType = collect($chunks)->groupBy('chunkable_type');
        $labels = [];

        foreach ($byType as $type => $group) {
            /** @var Collection<int, RetrievedChunkData> $group */
            $sourceIds = $group->pluck('chunkable_id')->unique()->values()->all();

            $resolved = match ($type) {
                'source_file' => $this->files($group, $sourceIds),
                'source_website' => $this->websitePages($group, $sourceIds),
                'source_text' => $this->texts($group, $sourceIds),
                'source_youtube_video' => $this->videos($group, $sourceIds),
                default => [],
            };

            foreach ($group as $chunk) {
                $labels[$chunk->id] = $resolved[$chunk->id] ?? new SourceLabelData(self::UNKNOWN, SourceKind::Text);
            }
        }

        return $labels;
    }

    /**
     * @param  Collection<int, RetrievedChunkData>  $chunks
     * @param  list<int>  $ids
     * @return array<int, SourceLabelData>
     */
    private function files(Collection $chunks, array $ids): array
    {
        $files = SourceFile::query()->whereIn('id', $ids)->get(['id', 'filename', 'mime_type'])->keyBy('id');

        return $chunks->mapWithKeys(function (RetrievedChunkData $chunk) use ($files): array {
            $file = $files->get($chunk->chunkable_id);

            if (! $file instanceof SourceFile) {
                return [];
            }

            $media = MimeType::isAudio($file->mime_type) || MimeType::isVideo($file->mime_type);

            return [$chunk->id => new SourceLabelData(
                label: $file->filename,
                kind: $media ? SourceKind::Media : SourceKind::File,
                page: MimeType::isPdf($file->mime_type) && $chunk->chunk_index > 0 ? $chunk->chunk_index : null,
            )];
        })->all();
    }

    /**
     * @param  Collection<int, RetrievedChunkData>  $chunks
     * @param  list<int>  $ids
     * @return array<int, SourceLabelData>
     */
    private function websitePages(Collection $chunks, array $ids): array
    {
        $sites = SourceWebsite::query()->whereIn('id', $ids)->pluck('url', 'id');

        $pageIdByChunk = ChunkReference::query()
            ->whereIn('chunk_id', $chunks->pluck('id')->all())
            ->where('referenceable_type', (new AgentWebsiteUrl)->getMorphClass())
            ->selectRaw('chunk_id, MIN(referenceable_id) AS page_id')
            ->groupBy('chunk_id')
            ->pluck('page_id', 'chunk_id');

        $pages = AgentWebsiteUrl::query()->whereIn('id', $pageIdByChunk->values()->all())->get(['id', 'url', 'title'])->keyBy('id');

        return $chunks->mapWithKeys(function (RetrievedChunkData $chunk) use ($sites, $pageIdByChunk, $pages): array {
            $page = $pages->get($pageIdByChunk->get($chunk->id));

            if ($page instanceof AgentWebsiteUrl) {
                return [$chunk->id => new SourceLabelData($page->title ?: $page->url, SourceKind::Website, $page->url)];
            }

            $site = $sites->get($chunk->chunkable_id);

            return filled($site) ? [$chunk->id => new SourceLabelData($site, SourceKind::Website, $site)] : [];
        })->all();
    }

    /**
     * @param  Collection<int, RetrievedChunkData>  $chunks
     * @param  list<int>  $ids
     * @return array<int, SourceLabelData>
     */
    private function texts(Collection $chunks, array $ids): array
    {
        $excerpts = SourceText::query()->whereIn('id', $ids)->pluck('excerpt', 'id');

        return $chunks->mapWithKeys(function (RetrievedChunkData $chunk) use ($excerpts): array {
            $excerpt = trim((string) preg_replace('/\s+/u', ' ', (string) $excerpts->get($chunk->chunkable_id)));

            return $excerpt === '' ? [] : [$chunk->id => new SourceLabelData(Str::limit($excerpt, self::TEXT_LABEL_LENGTH, preserveWords: true), SourceKind::Text)];
        })->all();
    }

    /**
     * @param  Collection<int, RetrievedChunkData>  $chunks
     * @param  list<int>  $ids
     * @return array<int, SourceLabelData>
     */
    private function videos(Collection $chunks, array $ids): array
    {
        $videos = SourceYoutubeVideo::query()->whereIn('id', $ids)->get(['id', 'title', 'url'])->keyBy('id');

        return $chunks->mapWithKeys(function (RetrievedChunkData $chunk) use ($videos): array {
            $video = $videos->get($chunk->chunkable_id);

            return $video instanceof SourceYoutubeVideo
                ? [$chunk->id => new SourceLabelData($video->title ?: $video->url, SourceKind::Video, $video->url)]
                : [];
        })->all();
    }
}
