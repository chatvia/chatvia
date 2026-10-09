<?php

namespace App\Http\Controllers\Agent\Source\Web;

use App\Http\Requests\Agent\Source\StartAgentWebsiteCrawlRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Crawl\StartAgentWebsiteCrawlAction;
use Domain\Knowledge\Source\Web\Actions\Source\DeleteSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The agent's websites: the list with training progress, adding a website
 * (starts discovery) and deleting one. Agent access is checked by the route
 * middleware; the `agent.sources.train.web` and `agent.sources.delete`
 * permissions too, and access to the website by its policy.
 */
class AgentWebsiteController
{
    /**
     * @return Collection<int, SourceWebsite>
     */
    public function index(Agent $agent): Collection
    {
        return $agent
            ->websites()
            ->select(['source_websites.id', 'url', 'status', 'source_websites.created_at', 'options', 'expected_chunks'])
            ->withCount(['urls', 'agents', 'chunks'])
            ->get()
            ->map(fn (SourceWebsite $website): SourceWebsite => $website->setAttribute('progress', $website->ingestionProgress()));
    }

    public function store(StartAgentWebsiteCrawlRequest $request, Agent $agent, StartAgentWebsiteCrawlAction $startCrawl): void
    {
        $startCrawl->execute($agent, $request->toData());
    }

    public function destroy(Request $request, Agent $agent, SourceWebsite $website, DeleteSourceWebsiteAction $delete): void
    {
        abort_if($request->user()->cannot('delete', $website), 404);

        $delete->execute($website);
    }
}
