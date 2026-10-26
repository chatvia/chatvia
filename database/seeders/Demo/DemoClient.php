<?php

namespace Database\Seeders\Demo;

/**
 * Where a demo conversation runs (a ConversationSource value) and the
 * visitor's device, as the chat log hands them to analytics with each
 * message, so the demo channel and device numbers are filled in.
 */
final readonly class DemoClient
{
    public function __construct(
        public string $source,
        public ?string $deviceType,
        public ?string $browser,
        public ?string $os,
    ) {}
}
