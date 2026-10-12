<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use App\Admin\Settings\MailProvider;
use Spatie\LaravelData\Data;

/**
 * How Chatvia sends email, as the admin setup asks for it: the provider, the
 * sender address, and the provider's settings (MailSettings property =>
 * value, only the ones it needs; {@see MailProvider::fields()}).
 */
final class SetupMailData extends Data
{
    /**
     * @param  array<string, string|int|null>  $values
     */
    public function __construct(
        public MailProvider $provider,
        public string $fromAddress,
        public array $values = [],
    ) {}
}
