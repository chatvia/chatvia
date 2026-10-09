<?php

namespace Domain\Knowledge\Source\Web\Actions\Url;

use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Support\Facades\DB;

/**
 * Removes several pages from a website in one go, each with the chunks only it
 * produced (see {@see DeleteAgentWebsiteUrlAction}). Ids of URLs that belong to
 * another website are ignored.
 */
class DeleteAgentWebsiteUrlsAction
{
    public function __construct(
        private readonly DeleteAgentWebsiteUrlAction $deleteUrl,
    ) {}

    /**
     * @param  list<int>  $urlIds
     * @return int how many URLs were removed
     */
    public function execute(SourceWebsite $website, array $urlIds): int
    {
        return DB::transaction(function () use ($website, $urlIds): int {
            $urls = $website->urls()->whereIn('id', $urlIds)->get();

            $urls->each(fn (AgentWebsiteUrl $url) => $this->deleteUrl->execute($url));

            return $urls->count();
        });
    }
}
