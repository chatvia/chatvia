<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use App\Admin\Settings\MailProvider;
use Spatie\LaravelData\Data;

/**
 * How an install sends email, as the admin setup asks for it: the provider,
 * the settings it needs ({@see MailProvider::fields()}), and the sender.
 */
final class MailSetupData extends Data
{
    /**
     * @param  array<string, string|int|null>  $values  the provider's settings, by MailSettings property; a blank secret keeps the saved one
     */
    public function __construct(
        public MailProvider $provider,
        public array $values = [],
        public ?string $fromAddress = null,
        public ?string $fromName = null,
    ) {}
}
