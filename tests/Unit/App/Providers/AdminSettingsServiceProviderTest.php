<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * A queue worker boots once and runs for hours, so AdminSettingsServiceProvider
 * applies the saved settings again before each job: a value saved in the
 * panel by another process reaches the next job.
 */
it('applies a change made elsewhere before a queued job runs', function (): void {
    // Another process saved a new key: the row changed, this process has
    // not seen it yet.
    DB::table('settings')->where('group', 'ai_providers')->where('name', 'mistral_api_key')
        ->update(['payload' => json_encode(Crypt::encrypt('saved-elsewhere'))]);
    cache()->flush();
    // A queue worker forgets scoped instances before each job.
    app()->forgetScopedInstances();

    // The closure is serialized onto the (sync) queue, so it reports back
    // through the cache.
    dispatch(function (): void {
        cache()->put('mistral-key-seen-by-job', config('services.mistral.api_key'));
    });

    expect(cache('mistral-key-seen-by-job'))->toBe('saved-elsewhere');
});
