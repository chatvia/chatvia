<?php

namespace App\Http\Controllers\Agents\Sources\Crawler;

use App\Models\Agent;
use App\Models\SourceWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

class AgentSourceWebsiteDeleteController
{
    public function __invoke(Request $request, Agent $agent, SourceWebsite $website)
    {
        abort_if(
            boolean: $request->user()->cannot('delete', $website),
            code: 404,
        );

        // Cancel the batch if the website is currently being trained
        if ($website->internal_chunking_batch_id) {
            $batch = Bus::findBatch($website->internal_chunking_batch_id);

            if ($batch && ! $batch->cancelled()) {
                $batch->cancel();
            }
        }

        // Delete all associated chunks
        $website->chunks()->delete();

        $website->delete();
    }
}
