<?php

use App\Marketing\Models\Changelog;

/**
 * The changelog lists releases newest first and groups each release's changes
 * under their type label; an unknown type is skipped rather than breaking the
 * page.
 */
it('groups each release\'s changes under their type label, newest release first', function (): void {
    $older = Changelog::query()->create(['version' => '1.0.0', 'release_date' => now()->subMonth()]);
    $newer = Changelog::query()->create(['version' => '1.1.0', 'release_date' => now()]);

    $newer->items()->create(['type' => 'bug_fixes', 'description' => 'Fixed the thing']);
    $newer->items()->create(['type' => 'something_else', 'description' => 'Hidden entry']);
    $older->items()->create(['type' => 'new_features', 'description' => 'First feature']);

    $this->get(route('changelog'))
        ->assertOk()
        ->assertSeeInOrder(['Version 1.1.0', 'Bug Fixes', 'Fixed the thing', 'Version 1.0.0', 'New Features', 'First feature'])
        ->assertDontSee('Hidden entry');
});
