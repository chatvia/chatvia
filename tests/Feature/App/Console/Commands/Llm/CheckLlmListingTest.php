<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

/**
 * `chatvia:llm:check-listing` runs the recheck of the models a provider
 * stopped serving (RecheckUnlistedLlmsAction, tested there) and says how many
 * providers it asked.
 */
it('rechecks the unlisted models from the command line', function (): void {
    Http::fake(['llm.test/v1/models' => Http::response(['data' => [['id' => 'back-model']]])]);
    $back = testLlm('back-model', attributes: ['unlisted_at' => now()]);

    $this->artisan('chatvia:llm:check-listing')->expectsOutputToContain('Checked 1 providers')->assertSuccessful();

    expect($back->fresh()->unlisted_at)->toBeNull();
});
