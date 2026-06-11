<?php

namespace Domain\Knowledge\Source\Text\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\DB;

class DeleteAgentSourceTextAction
{
    public function execute(Agent $agent, SourceText $text): void
    {
        // Chunks (incl. summary) cleaned up by Chunkable::bootChunkable() via
        // the deleting event.
        DB::transaction(function () use ($agent, $text): void {
            $text->agents()->detach($agent->id);
            $text->delete();
        });
    }
}
