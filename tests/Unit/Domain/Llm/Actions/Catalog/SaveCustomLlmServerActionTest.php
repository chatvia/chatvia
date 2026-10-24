<?php

declare(strict_types=1);

use Domain\Llm\Actions\Catalog\SaveCustomLlmServerAction;
use Domain\Llm\Data\Catalog\CustomLlmServerData;

/**
 * A server of your own keeps its address and key on its row; saving it again
 * without a key keeps the stored one.
 */
it('saves a server of your own, keeping its key when none is given', function (): void {
    $server = app(SaveCustomLlmServerAction::class)->execute(new CustomLlmServerData(' Office ', 'https://llm.test/v1/', 'key-1', ['X-Team' => 'a']));
    app(SaveCustomLlmServerAction::class)->execute(new CustomLlmServerData('Office', 'https://llm.test/v2'), $server);

    expect($server->fresh()->name)->toBe('Office')
        ->and($server->fresh()->base_url)->toBe('https://llm.test/v2')
        ->and($server->fresh()->api_key)->toBe('key-1')
        ->and($server->fresh()->headers)->toBeNull();
});
