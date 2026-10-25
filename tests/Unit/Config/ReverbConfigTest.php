<?php

declare(strict_types=1);

use Tests\Support\Config\ConfigFile;

/**
 * Reverb relays client events (the typing whispers) only from a connection
 * subscribed to the channel, on the main app and on the optional extra app.
 * Its own fallback is 'all', which would let any socket holding the public app
 * key push events into a chat it never joined.
 */
it('accepts client events only from channel members on every app', function (): void {
    $apps = ConfigFile::with('reverb.php', ['REVERB_EXTRA_APP_ID' => 'extra'])['apps']['apps'];

    expect($apps)->toHaveCount(2)
        ->and(array_column($apps, 'accept_client_events_from'))->toBe(['members', 'members']);
});
