<?php

declare(strict_types=1);

/**
 * config/sentry.php: what the server's error reports may carry.
 */
it('keeps request bodies and personal data out of the server\'s error reports', function (): void {
    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.max_request_body_size'))->toBe('none');
});
