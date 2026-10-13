<?php

use Domain\Agent\Models\Agent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // Agent brand images (avatar/logo) live on the CDN-backed `assets` disk.
    Storage::fake('assets');
});

it('uploads an agent brand image to the assets disk and persists its path', function (): void {
    $agent = Agent::factory()->create();

    $agent->setSetting('avatar', UploadedFile::fake()->image('avatar.png', 256, 256), 'appearance');

    $path = $agent->modelSettings()->where('key', 'avatar')->where('group', 'appearance')->value('value');

    expect($path)->toBeString()
        ->and($path)->toStartWith('workspaces/'.$agent->workspace->external_id.'/agents/'.$agent->external_id.'/settings/appearance/');

    Storage::disk('assets')->assertExists($path);
});

it('reads the stored brand image back as an assets-disk URL', function (): void {
    $agent = Agent::factory()->create();

    $agent->setSetting('avatar', UploadedFile::fake()->image('avatar.png', 256, 256), 'appearance');
    $path = $agent->modelSettings()->where('key', 'avatar')->where('group', 'appearance')->value('value');

    // The avatar lives on `assets`, so its URL must resolve through that disk's
    // pull zone — not the default disk (which can't even produce a URL).
    $agent->refresh();

    expect($agent->settings('appearance')['avatar'])->toBe(Storage::disk('assets')->url($path));
});

it('replaces the previous brand image and removes the old file once the new upload succeeds', function (): void {
    $agent = Agent::factory()->create();

    $agent->setSetting('logo', UploadedFile::fake()->image('logo-1.png', 256, 256), 'appearance');
    $first = $agent->modelSettings()->where('key', 'logo')->where('group', 'appearance')->value('value');
    Storage::disk('assets')->assertExists($first);

    $agent->setSetting('logo', UploadedFile::fake()->image('logo-2.png', 256, 256), 'appearance');
    $second = $agent->modelSettings()->where('key', 'logo')->where('group', 'appearance')->value('value');

    expect($second)->not->toBe($first);
    Storage::disk('assets')->assertExists($second);
    Storage::disk('assets')->assertMissing($first);
});
