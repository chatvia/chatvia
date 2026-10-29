<?php

use App\Admin\InitialsAvatarProvider;
use Illuminate\Support\Str;

/**
 * The admin panel draws each person's avatar from their initials, as an SVG
 * data URL made on the server: no third-party avatar service sees the names,
 * and markup in a name never reaches the SVG.
 */
it('keeps markup in a name out of the SVG and never points at a third-party avatar service', function (): void {
    $url = (new InitialsAvatarProvider)->forName('<script>alert(1)</script>');

    $svg = base64_decode(Str::after($url, 'data:image/svg+xml;base64,'));

    expect($url)->toStartWith('data:image/svg+xml;base64,')
        ->not->toContain('http')
        ->and($svg)->toContain('>S</text>')
        ->not->toContain('<script')
        ->and(simplexml_load_string($svg))->not->toBeFalse();
});
