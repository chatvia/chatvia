<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account\Audience;

/**
 * One channel's conversations and its share of the total, in percent.
 */
final readonly class ChannelShareData
{
    public function __construct(
        /** The stored conversation source, e.g. 'widget' or 'whatsapp'. */
        public ?string $source,
        public string $label,
        public int $conversations,
        public float $pct,
    ) {}
}
