<?php

declare(strict_types=1);

namespace Domain\Evaluation\Queries;

use Domain\Agent\Models\Agent;
use Domain\Evaluation\Data\Resources\EvalSourceCoverageData;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Models\EvalResult;
use Domain\Evaluation\Models\EvalRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

/**
 * Which of an agent's files, texts and websites its last finished eval run
 * drew on (from each result's `source_refs`), and which none of the cases
 * reached. Null before the agent's first finished run.
 */
class EvalSourceCoverageQuery
{
    /** At most this many untested names are listed. */
    private const int MAX_UNTESTED = 20;

    public function for(Agent $agent): ?EvalSourceCoverageData
    {
        $runId = EvalRun::query()
            ->where('agent_id', $agent->id)
            ->where('status', EvalRunStatus::Completed)
            ->latest('id')
            ->value('id');

        if ($runId === null) {
            return null;
        }

        $tested = EvalResult::query()
            ->where('run_id', $runId)
            ->whereNotNull('source_refs')
            ->pluck('source_refs')
            ->flatten()
            ->flip();

        $sources = $this->sources($agent);
        $untested = $sources->reject(static fn (string $name, string $ref): bool => $tested->has($ref));

        return new EvalSourceCoverageData(
            total: $sources->count(),
            tested: $sources->count() - $untested->count(),
            untested: $untested->take(self::MAX_UNTESTED)->values()->all(),
        );
    }

    /**
     * The agent's files, texts and websites as `{morph alias}:{id}` => name.
     *
     * @return Collection<string, string>
     */
    private function sources(Agent $agent): Collection
    {
        $named = static fn (Model $source, string $name): array => ["{$source->getMorphClass()}:{$source->getKey()}" => $name];

        return collect()
            ->merge($agent->files()->get(['source_files.id', 'filename'])->mapWithKeys(static fn ($file): array => $named($file, $file->filename)))
            ->merge($agent->texts()->get(['source_texts.id', 'excerpt'])->mapWithKeys(static fn ($text): array => $named($text, Str::limit(Str::squish($text->excerpt), 60))))
            ->merge($agent->websites()->get(['source_websites.id', 'url'])->mapWithKeys(static fn ($site): array => $named($site, Str::after(Uri::of($site->url)->host(), 'www.'))));
    }
}
