<?php

namespace Domain\Source\Web\Actions\Url;

use Domain\Source\Web\Models\SourceWebsite;

class BulkDeleteWebsiteUrlsAction
{
    /**
     * @param  array<int, int>  $linkIds
     */
    public function execute(SourceWebsite $website, array $linkIds): int
    {
        return $website->urls()
            ->whereIn('id', $linkIds)
            ->delete();
    }
}
