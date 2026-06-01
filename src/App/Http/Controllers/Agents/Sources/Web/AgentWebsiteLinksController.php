<?php

namespace App\Http\Controllers\Agents\Sources\Web;

use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Actions\Url\DeleteAgentWebsiteUrlAction;
use Domain\Sources\Web\Models\AgentWebsiteUrl;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;

class AgentWebsiteLinksController
{
    public function index(Request $request, Agent $agent, SourceWebsite $website): Paginator
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $query = $website->urls()->select([
            'id',
            'source_website_id',
            'url',
            'status',
            'created_at',
            'crawled_at',
        ]);

        if ($request->filled('q')) {
            $query->where('url', 'like', '%'.$request->input('q').'%');
        }

        return $query->latest('crawled_at')->simplePaginate(50);
    }

    public function destroy(
        Request $request,
        Agent $agent,
        SourceWebsite $website,
        AgentWebsiteUrl $link,
        DeleteAgentWebsiteUrlAction $delete,
    ): void {
        abort_if($request->user()->cannot('delete', $website), 404);

        $delete->execute($link);
    }
}
