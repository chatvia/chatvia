<?php

declare(strict_types=1);

namespace Domain\Channel\Data;

use Spatie\LaravelData\Data;

/**
 * A Facebook Page the user granted through Facebook Login for Business, with
 * its own long-lived page access token. The token never reaches the browser.
 */
class MetaPageData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $access_token,
    ) {}
}
