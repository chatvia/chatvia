<?php

use Domain\Knowledge\Document\Conversion\Markdown\RtfReader;
use Illuminate\Support\Facades\File;

/**
 * The built-in RTF reader: files are written here as RTF strings, one case
 * per feature that pandoc's RTF reader handles or loses. Line breaks in the
 * strings are not text: RTF ignores them.
 */
function rtfrRead(string $rtf): string
{
    $path = create_temp_file('document.rtf', $rtf);

    try {
        return new RtfReader()->toMarkdown($path);
    } finally {
        File::delete($path);
    }
}

it('reads paragraphs, escaped 8-bit characters and Unicode characters', function (): void {
    $rtf = <<<'RTF'
        {\rtf1\ansi\ansicpg1252\deff0{\fonttbl{\f0\fswiss Helvetica;}}
        \pard Caf\'e9 prices\par
        \pard\uc1 Bullet \u8226? and smile \u-10179?\u-8704?\par
        \pard\uc0 Dash\u8212 done\line next line\tab tab\par
        \pard Braces \{ and \} and a backslash \\\par}
        RTF;

    expect(rtfrRead($rtf))->toBe("Café prices\n\nBullet • and smile 😀\n\nDash—done\nnext line tab\n\nBraces { and } and a backslash \\");
});

it('decodes 8-bit text in the code page of its font', function (): void {
    $rtf = <<<'RTF'
        {\rtf1\ansi\ansicpg1252{\fonttbl{\f0 Arial;}{\f1\fcharset204 Arial Cyr;}{\f2\fcharset128 MS Mincho;}}
        \pard\f1 \'cf\'f0\'e8\'e2\'e5\'f2\par
        \pard\f2 \'93\'fa\'96\'7b\'8c\'ea\par}
        RTF;

    expect(rtfrRead($rtf))->toBe("Привет\n\n日本語");
});
