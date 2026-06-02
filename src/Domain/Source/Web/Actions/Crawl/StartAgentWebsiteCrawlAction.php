<?php

namespace Domain\Source\Web\Actions\Crawl;

use App\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Source\Web\Data\Crawler\CrawlerData;
use Domain\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Source\Web\Models\SourceWebsite;
use Domain\Team\Exceptions\InviteRejectedException;
use Illuminate\Support\Facades\Http;
use Throwable;

class StartAgentWebsiteCrawlAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(Agent $agent, User $user, CrawlerData $data): SourceWebsite
    {
        if ($agent->websites()->where('url', $data->url)->exists()) {
            throw new InviteRejectedException('url', 'This website has already been added to this agent.');
        }

        try {
            $response = Http::withOptions(['allow_redirects' => false])->get($data->url)->throw();
        } catch (Throwable $throwable) {
            report($throwable);
            throw new InviteRejectedException('url', 'Failed to fetch the website. Please check the URL and try again.');
        }

        if ($response->redirect()) {
            $data->url = $response->header('Location');
        }

        $website = SourceWebsite::create([
            'url' => $data->url,
            'user_id' => $user->id,
            'status' => 'crawling',
            'options' => $data->exclude('url')->toArray(),
        ]);

        $agent->websites()->attach($website->id);

        WebsiteCrawlStarted::dispatch($website, $agent);
        WebsiteCrawlerJob::dispatch($website, $data, $agent);

        return $website;
    }
}
