<?php

namespace App\Http\Controllers\Agent\Source\Web;

use App\Http\Requests\Agent\Source\StartAgentWebsiteCrawlRequest;
use App\Http\Requests\Agent\Source\UpdateAgentWebsiteRequest;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Actions\Crawl\StartAgentWebsiteCrawlAction;
use Domain\Knowledge\Source\Web\Actions\Refresh\SetWebsiteRefreshIntervalAction;
use Domain\Knowledge\Source\Web\Actions\Source\DeleteSourceWebsiteAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * The agent's websites: the list with training progress (newest first, one
 * page at a time), adding a website (starts discovery) and deleting one. Agent
 * access is checked by the route middleware; the `agent.sources.train.web`
 * and `agent.sources.delete` permissions too, and access to the website by
 * its policy.
 */
class AgentWebsiteController
{
    private const int PER_PAGE = 50;

    /**
     * @return LengthAwarePaginator<int, SourceWebsite>
     */
    public function index(Agent $agent): LengthAwarePaginator
    {
        // The relation's own query: paginating the relation would add `*`
        // and the pivot columns back to the select.
        $websites = $agent
            ->websites()
            ->getQuery()
            ->select(['source_websites.id', 'url', 'status', 'source_websites.created_at', 'options', 'expected_chunks', 'refresh_interval', 'refreshed_at'])
            ->withCount(['urls', 'agents'])
            ->orderByDesc('source_websites.id')
            ->paginate(self::PER_PAGE);

        // Only a website that is training shows progress, which needs its chunk count.
        $websites->getCollection()
            ->filter(fn (SourceWebsite $website): bool => $website->status === SourceStatus::Processing)
            ->loadCount('chunks');

        return $websites->through(fn (SourceWebsite $website): SourceWebsite => $website->setAttribute('progress', $website->ingestionProgress()));
    }

    public function store(StartAgentWebsiteCrawlRequest $request, Agent $agent, StartAgentWebsiteCrawlAction $startCrawl): void
    {
        $startCrawl->execute($agent, $request->toData());
    }

    public function update(UpdateAgentWebsiteRequest $request, Agent $agent, SourceWebsite $website, SetWebsiteRefreshIntervalAction $setRefresh): void
    {
        // {website} binds by id inside the workspace: it must also be one of
        // this agent's websites, or a teammate on this agent could change
        // another agent's.
        abort_unless($agent->websites()->whereKey($website->id)->exists(), 404);
        abort_if($request->user()->cannot('update', $website), 404);

        $setRefresh->execute($website, $request->refreshInterval());
    }

    public function destroy(Request $request, Agent $agent, SourceWebsite $website, DeleteSourceWebsiteAction $delete): void
    {
        // {website} binds by id inside the workspace: it must also be one of
        // this agent's websites, or a teammate on this agent could change
        // another agent's.
        abort_unless($agent->websites()->whereKey($website->id)->exists(), 404);
        abort_if($request->user()->cannot('delete', $website), 404);

        $delete->execute($website);
    }
}
