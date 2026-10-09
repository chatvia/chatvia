<?php

namespace Domain\Channel\Instagram\Data;

use Spatie\LaravelData\Data;

/**
 * An Instagram professional account the user granted through Facebook Login
 * for Business: the account (id + username) and the Facebook Page it is linked
 * to, whose page access token sends its DMs. The token never reaches the
 * browser.
 */
class InstagramAccountData extends Data
{
    public function __construct(
        public string $page_id,
        public string $page_name,
        public string $page_access_token,
        public string $ig_id,
        public string $ig_username,
    ) {}
}
