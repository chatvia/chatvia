<?php

declare(strict_types=1);

use Domain\Integration\Tool\Enums\ToolCallOutcome;

it('flags only human-decided outcomes as gated', function (): void {
    expect(ToolCallOutcome::Approved->wasGated())->toBeTrue()
        ->and(ToolCallOutcome::Denied->wasGated())->toBeTrue()
        ->and(ToolCallOutcome::Auto->wasGated())->toBeFalse()
        ->and(ToolCallOutcome::RateLimited->wasGated())->toBeFalse()
        ->and(ToolCallOutcome::NotOffered->wasGated())->toBeFalse();
});
