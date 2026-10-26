<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;

/**
 * A new agent analyzes sentiment, tone and topics from its first chats. The
 * tests start with them off (TestCase), so this reads the shipped defaults
 * from the config file itself.
 */
it('turns sentiment, tone and topics on for a new agent', function (): void {
    $defaults = (require config_path('model-settings.php'))['defaults'][Agent::class]['analytics'];

    expect($defaults)->toMatchArray(['chatlog' => true, 'sentiment' => true, 'tone' => true, 'topics' => true]);
});
