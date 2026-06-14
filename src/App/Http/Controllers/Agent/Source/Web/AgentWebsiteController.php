<?php

namespace App\Http\Controllers\Agent\Source\Web;

use App\Http\Requests\Agent\Source\StartAgentWebsiteCrawlRequest;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Crawl\StartAgentWebsiteCrawlAction;
use Domain\Knowledge\Source\Web\Actions\Source\DeleteSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgentWebsiteController
{
    public function index(Request $request, Agent $agent): Collection
    {
        $websites = $agent
            ->websites()
            ->select(['source_websites.id', 'url', 'status', 'source_websites.created_at', 'options', 'expected_chunks'])
            ->withCount(['urls', 'agents', 'chunks'])
            ->get();

        return collect($websites)->map(function ($website) {
            $website['progress'] = $website->ingestionProgress();

            return $website;
        });
    }

    public function store(StartAgentWebsiteCrawlRequest $request, Agent $agent, StartAgentWebsiteCrawlAction $startCrawl): void
    {
        /** @var User $user */
        $user = $request->user();

        $startCrawl->execute($agent, $user, $request->toData());
    }

    public function destroy(Request $request, Agent $agent, SourceWebsite $website, DeleteSourceWebsiteAction $delete): void
    {
        abort_if($request->user()->cannot('delete', $website), 404);

        $delete->execute($website);
    }
}
