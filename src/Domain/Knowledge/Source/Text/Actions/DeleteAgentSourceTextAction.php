<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Text\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a text source. Its chunks (summary included) go first, in batches
 * and outside the transaction; the Chunkable trait's `deleting` hook removes
 * anything left inside it.
 */
class DeleteAgentSourceTextAction
{
    public function execute(Agent $agent, SourceText $text): void
    {
        $text->deleteChunks();

        DB::transaction(function () use ($agent, $text): void {
            $text->agents()->detach($agent->id);
            $text->delete();
        });
    }
}
