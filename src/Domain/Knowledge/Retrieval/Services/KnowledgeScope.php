<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Services;

use Domain\Agent\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

/**
 * Short names of what an agent's knowledge covers: its files (without the
 * extension), websites (the host), texts (their start), videos and Q&A
 * titles. When nothing matches a question, the model is given these so it can
 * tell the visitor what it can help with instead of a bare "I don't know".
 * Cached for a few minutes per agent; a source added since shows up then.
 */
class KnowledgeScope
{
    /** At most this many names go to the model, so the hint stays small. */
    private const int MAX_NAMES = 15;

    /** Each name is cut to this many characters. */
    private const int NAME_LENGTH = 80;

    private const int CACHE_SECONDS = 600;

    /**
     * @return list<string>
     */
    public function names(Agent $agent): array
    {
        /** @var list<string> */
        return Cache::remember("knowledge-scope:{$agent->id}", self::CACHE_SECONDS, fn (): array => $this->collect($agent));
    }

    /**
     * @return list<string>
     */
    private function collect(Agent $agent): array
    {
        $names = collect()
            ->merge($agent->qas()->latest('id')->limit(self::MAX_NAMES)->pluck('title'))
            ->merge($agent->files()->latest('source_files.id')->limit(self::MAX_NAMES)->pluck('filename')
                ->map(static fn (string $filename): string => Str::of(File::name($filename))->replace(['_', '-'], ' ')->squish()->value()))
            ->merge($agent->websites()->limit(self::MAX_NAMES)->pluck('url')
                ->map(static fn (string $url): string => Str::after(Uri::of($url)->host(), 'www.')))
            ->merge($agent->youtubeVideos()->limit(self::MAX_NAMES)->get(['title', 'url'])
                ->map(static fn (object $video): string => (string) ($video->title ?: $video->url)))
            ->merge($agent->texts()->limit(self::MAX_NAMES)->pluck('excerpt'));

        return $this->tidy($names);
    }

    /**
     * @param  Collection<int, mixed>  $names
     * @return list<string>
     */
    private function tidy(Collection $names): array
    {
        return $names
            ->map(static fn (mixed $name): string => Str::of((string) $name)->squish()->limit(self::NAME_LENGTH, preserveWords: true)->value())
            ->filter(static fn (string $name): bool => $name !== '')
            ->unique(static fn (string $name): string => Str::lower($name))
            ->take(self::MAX_NAMES)
            ->values()
            ->all();
    }
}
