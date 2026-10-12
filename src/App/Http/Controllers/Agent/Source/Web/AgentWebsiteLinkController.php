<?php

namespace App\Http\Controllers\Agent\Source\Web;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Url\DeleteAgentWebsiteUrlAction;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;

/**
 * The discovered pages (URLs) of one of the agent's websites: a searchable,
 * paginated list, and removing one page with the chunks only it produced.
 */
class AgentWebsiteLinkController
{
    /**
     * @return Paginator<int, AgentWebsiteUrl>
     */
    public function index(Request $request, Agent $agent, SourceWebsite $website): Paginator
    {
        // {website} binds globally by id — confirm it belongs to this agent so
        // a caller can't list another tenant's crawled URLs.
        abort_if(! $agent->websites()->whereKey($website->id)->exists(), 404);

        $query = $website->urls()->select([
            'id',
            'source_website_id',
            'url',
            'status',
            'created_at',
            'crawled_at',
        ]);

        if ($request->filled('q')) {
            // ilike: case-insensitive matches user expectation for URLs. A
            // website has at most a few thousand URLs, so scanning them is cheap.
            $query->where('url', 'ilike', '%'.$request->input('q').'%');
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

        // {link} binds globally — ensure it belongs to {website} so a user
        // can't delete a URL row from a foreign website.
        abort_if($link->source_website_id !== $website->id, 404);

        $delete->execute($link);
    }
}
