<?php

use Illuminate\Support\Facades\DB;

/**
 * The widget's options moved from the platform settings to their own group:
 * a value an admin saved on Settings → Platform is still in use afterwards.
 */
it('moves the saved widget values from the platform group to the widget group', function (): void {
    $migration = require database_path('settings/2026_10_25_130000_move_widget_settings.php');
    $migration->down();

    $payload = fn (string $group, string $name): mixed => DB::table('settings')->where('group', $group)->where('name', $name)->value('payload');
    DB::table('settings')->where('group', 'platform')->where('name', 'widget_script_url')
        ->update(['payload' => json_encode('https://cdn.example.com/embed.js')]);
    DB::table('settings')->where('group', 'platform')->where('name', 'widget_stream_per_minute')
        ->update(['payload' => json_encode(30)]);

    $migration->up();

    expect(DB::table('settings')->where('group', 'platform')->where('name', 'like', 'widget_%')->exists())->toBeFalse()
        ->and(json_decode((string) $payload('widget', 'script_url')))->toBe('https://cdn.example.com/embed.js')
        ->and(json_decode((string) $payload('widget', 'stream_per_minute')))->toBe(30)
        ->and(json_decode((string) $payload('widget', 'allowed_origins')))->toBeNull()
        ->and(json_decode((string) $payload('widget', 'stream_per_hour')))->toBeNull();
});
