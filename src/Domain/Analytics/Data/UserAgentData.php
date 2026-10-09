<?php

declare(strict_types=1);

namespace Domain\Analytics\Data;

/**
 * What a browser user-agent string says about the visitor's device: device
 * type (Desktop, iPhone, Android Phone, …), browser and operating system.
 * All null when there was no user agent.
 */
final readonly class UserAgentData
{
    public function __construct(
        public ?string $deviceType = null,
        public ?string $browser = null,
        public ?string $os = null,
    ) {}
}
