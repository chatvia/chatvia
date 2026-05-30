<?php

namespace Domain\Sources\Text\Actions;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Data\TrainedTextRowData;
use Domain\Sources\Text\Models\SourceText;
use Illuminate\Support\Collection;

/**
 * Every ingested text in the user's account that's not already attached to the
 * given agent — feeds the "Attach an existing text" list on the agent page.
 */
class ListAttachableTextsForAgentAction
{
    /**
     * @return Collection<int, TrainedTextRowData>
     */
    public function execute(User $user, Agent $agent): Collection
    {
        $currentIds = $agent->texts()->pluck('source_texts.id');

        return SourceText::query()
            ->where('user_id', $user->id)
            ->where('status', 'ingested')
            ->whereNotIn('id', $currentIds)
            ->with(['agents' => fn ($q) => $q->select('agents.id', 'agents.name')])
            ->get()
            ->map(fn (SourceText $text): TrainedTextRowData => TrainedTextRowData::fromModel($text));
    }
}
