<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Sources;

use Domain\Agent\Queries\AgentSourcePageQuery;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Spatie\LaravelData\Data;

/**
 * One keyset page of an agent's files, texts and websites, newest first, and
 * the cursor of the next page. Built by {@see AgentSourcePageQuery}.
 */
class AgentSourcePageData extends Data
{
    /**
     * @param  list<SourceFile|SourceText|SourceWebsite>  $sources
     * @param  string|null  $next_cursor  sent back as `cursor` for the next page; null on the last page
     */
    public function __construct(
        public array $sources,
        public ?string $next_cursor,
    ) {}
}
