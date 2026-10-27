<?php

namespace Domain\Knowledge\Source\Text\Queries;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Data\TrainedTextRowData;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Every ingested text in the user's workspace that's not already attached to
 * the given agent — feeds the "Attach an existing text" list on the agent page.
 */
class AttachableTextsQuery
{
    /**
     * @return Collection<int, TrainedTextRowData>
     */
    public function get(User $user, Agent $agent): Collection
    {
        $currentIds = $agent->texts()->pluck('source_texts.id');

        // Only what a row needs: the excerpt, and the checksum and path the
        // word count is read with.
        return SourceText::query()
            ->select(['id', 'excerpt', 'checksum', 'filepath'])
            // Texts belong to the workspace owner, not to the teammate viewing them.
            ->where('workspace_id', $user->workspace()->id)
            ->where('status', SourceStatus::Completed)
            ->whereNotIn('id', $currentIds)
            ->with(['agents' => fn ($q) => $q->select('agents.id', 'agents.name')])
            ->get()
            ->map(fn (SourceText $text): TrainedTextRowData => TrainedTextRowData::fromModel($text, $this->wordCount($text)));
    }

    /**
     * The full text lives in storage, not in the row. Count it once per
     * content checksum so the list doesn't re-read every file on each open.
     */
    private function wordCount(SourceText $text): int
    {
        return Cache::rememberForever('knowledge:text:word-count:'.$text->checksum, function () use ($text): int {
            try {
                $content = (string) Storage::get($text->filepath);
            } catch (Throwable $e) {
                report($e);

                return 0;
            }

            return Str::of($content)->trim()->split('/\s+/u', -1, PREG_SPLIT_NO_EMPTY)->count();
        });
    }
}
