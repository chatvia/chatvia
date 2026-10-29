<?php

use App\Admin\Update\Data\ReleaseData;
use App\Admin\Update\Services\AvailableUpdate;
use Illuminate\Support\Facades\Http;

/**
 * The daily update check (`chatvia:update:check`): it reads the release tags
 * of the repository and keeps the newest release for the admin dashboard,
 * and asks nothing while checks are off.
 */
beforeEach(function (): void {
    config([
        'chatvia.updates.check' => true,
        'chatvia.updates.repository' => 'https://github.com/chatvia/chatvia',
        'chatvia.updates.version_file' => create_temp_file('VERSION', "1.4.2\n"),
    ]);
});

/** Git's ref list (smart HTTP), with each tag and its peeled line. */
function cfuRefs(string ...$tags): string
{
    return collect($tags)
        ->flatMap(fn (string $tag): array => ["0000aaaa refs/tags/{$tag}\n", "0000bbbb refs/tags/{$tag}^{}\n"])
        ->prepend("001e# service=git-upload-pack\n0000cccc HEAD\0multi_ack\n")
        ->implode('');
}

it('finds the newest release by version, leaving out release candidates', function (): void {
    Http::fake(['github.com/chatvia/chatvia.git/info/refs*' => Http::response(cfuRefs('v1.4.2', 'v1.10.0', 'v1.9.3', 'v2.0.0-rc.1'))]);

    $this->artisan('chatvia:update:check')->assertSuccessful();

    expect(app(AvailableUpdate::class)->get())
        ->toEqual(new ReleaseData(version: '1.10.0', url: 'https://github.com/chatvia/chatvia/releases/tag/v1.10.0'));
});

it('asks nothing and drops the last answer when checks are off', function (): void {
    app(AvailableUpdate::class)->remember(new ReleaseData(version: '1.5.0', url: 'https://github.com/chatvia/chatvia/releases/tag/v1.5.0'));
    config(['chatvia.updates.check' => false]);
    Http::fake();

    $this->artisan('chatvia:update:check')->assertSuccessful();

    config(['chatvia.updates.check' => true]);
    expect(app(AvailableUpdate::class)->get())->toBeNull();
    Http::assertNothingSent();
});
