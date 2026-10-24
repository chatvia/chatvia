<?php

use App\Admin\InitialsAvatarProvider;
use Illuminate\Support\Str;

/**
 * The admin panel draws each person's avatar from their initials, as an SVG
 * data URL made on the server: no third-party avatar service sees the names,
 * and markup in a name never reaches the SVG.
 */

/** The SVG inside the avatar's data URL. */
function iapSvg(string $name): string
{
    $url = (new InitialsAvatarProvider)->forName($name);

    expect($url)->toStartWith('data:image/svg+xml;base64,');

    return base64_decode(Str::after($url, 'data:image/svg+xml;base64,'));
}

it('draws the first and last initials', function (string $name, string $initials): void {
    expect(iapSvg($name))->toContain(">{$initials}</text>");
})->with([
    'two words' => ['Demo Admin', 'DA'],
    'three words' => ['Anna Maria Berg', 'AB'],
    'one word' => ['ahmad', 'A'],
    'leading punctuation' => ['[SYSTEM] Admin', 'SA'],
    'non-latin' => ['Émile Zola', 'ÉZ'],
    'blank' => ['   ', '?'],
]);

it('keeps markup in a name out of the SVG', function (): void {
    $svg = iapSvg('<script>alert(1)</script>');

    expect($svg)->toContain('>S</text>')
        ->and($svg)->not->toContain('<script')
        ->and(simplexml_load_string($svg))->not->toBeFalse();
});

it('never points at a third-party avatar service', function (): void {
    expect((new InitialsAvatarProvider)->forName('Demo Admin'))->not->toContain('http');
});
