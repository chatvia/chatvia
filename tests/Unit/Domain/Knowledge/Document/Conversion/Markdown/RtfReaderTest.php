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

it('drops a Unicode value no character has, instead of failing the file', function (): void {
    $rtf = <<<'RTF'
        {\rtf1\ansi\deff0{\fonttbl{\f0\fswiss Helvetica;}}
        \pard\uc1 Before \u99999999?after and \u-99999?end\par}
        RTF;

    expect(rtfrRead($rtf))->toBe('Before after and end');
});

it('decodes 8-bit text in the code page of its font', function (): void {
    $rtf = <<<'RTF'
        {\rtf1\ansi\ansicpg1252{\fonttbl{\f0 Arial;}{\f1\fcharset204 Arial Cyr;}{\f2\fcharset128 MS Mincho;}}
        \pard\f1 \'cf\'f0\'e8\'e2\'e5\'f2\par
        \pard\f2 \'93\'fa\'96\'7b\'8c\'ea\par}
        RTF;

    expect(rtfrRead($rtf))->toBe("Привет\n\n日本語");
});

it('makes headings from outline levels and from heading and Title styles', function (): void {
    $rtf = <<<'RTF'
        {\rtf1\ansi{\stylesheet{\s0 Normal;}{\s1\outlinelevel0 heading 1;}{\s2\sbasedon1 Chapter;}{\s3 Title;}{\*\cs10 Default Paragraph Font;}}
        \pard\s3 Visitor Policy\par
        \pard\s1 Scope\par
        \pard\s2 Rules\par
        \pard\outlinelevel2 Parking\par
        \pard\s0 Body text.\par}
        RTF;

    expect(rtfrRead($rtf))->toBe("# Visitor Policy\n\n# Scope\n\n# Rules\n\n### Parking\n\nBody text.");
});

it('makes list items from the number or bullet written before each one', function (): void {
    $rtf = <<<'RTF'
        {\rtf1\ansi
        \pard\ls1\ilvl0{\listtext\tab 1.\tab}Sign in.\par
        \pard\ls1\ilvl1{\listtext\tab \bullet \tab}Show the badge.\par
        \pard\ls1\ilvl0{\listtext\tab 2.\tab}Wear it.\par
        \pard After the list.\par
        \pard\ls2{\pntext\'b7\tab}A bullet.\par}
        RTF;

    expect(rtfrRead($rtf))->toBe("1. Sign in.\n    - Show the badge.\n2. Wear it.\n\nAfter the list.\n\n- A bullet.");
});

it('reads tables as pipe tables, a one-column table as paragraphs, and a nested table into its cell', function (): void {
    $rtf = <<<'RTF'
        {\rtf1\ansi
        \trowd\cellx2000\cellx4000\pard\intbl Role\cell Phone\cell\row
        \trowd\cellx2000\cellx4000\pard\intbl Gate\cell 2200\par extension\cell\row
        \pard Between.\par
        \trowd\cellx4000\pard\intbl A boxed note.\cell\row
        \pard Again.\par
        \trowd\cellx2000\cellx4000\pard\intbl Outer\cell
        \pard\intbl\itap2 Day\nestcell Night\nestcell{\*\nesttableprops\trowd\nestrow}
        \pard\intbl\itap1\cell\row
        \pard End.\par}
        RTF;

    expect(rtfrRead($rtf))->toBe(implode("\n\n", [
        "| Role | Phone |\n| --- | --- |\n| Gate | 2200<br>extension |",
        'Between.',
        'A boxed note.',
        'Again.',
        "| Outer | Day<br>Night |\n| --- | --- |",
        'End.',
    ]));
});

it('reads footnotes as numbered marks listed at the end', function (): void {
    $rtf = <<<'RTF'
        {\rtf1\ansi
        \pard Call 112.{\super\chftn}{\footnote\pard\plain{\super\chftn} Then the gate.\par}\par
        \pard Report fires.{\super\chftn}{\*\footnote\chftn\pard Within an hour.\par}\par}
        RTF;

    expect(rtfrRead($rtf))->toBe("Call 112.[^1]\n\nReport fires.[^2]\n\n[^1]: Then the gate.\n\n[^2]: Within an hour.");
});

it('keeps field results and leaves out field codes, hidden and deleted text, pictures and headers', function (): void {
    $rtf = <<<'RTF'
        {\rtf1\ansi{\info{\title Not body text}}{\header Page header\par}
        \pard See {\field{\*\fldinst HYPERLINK "https://example.com"}{\fldrslt the visitor page}}.\par
        \pard Visible {\v hidden }{\deleted removed }text.\par
        \pard {\pict\pngblip 89504e470d0a}{\*\shppict{\pict\bin4 {}\}}}Picture gone.\par
        \pard {\upr{Old text}{\*\ud{New text}}}\par}
        RTF;

    expect(rtfrRead($rtf))->toBe("See the visitor page.\n\nVisible text.\n\nPicture gone.\n\nNew text");
});

it('refuses a file that is not RTF', function (): void {
    expect(fn (): string => rtfrRead('Plain text, not RTF.'))->toThrow(RuntimeException::class, 'not an RTF document');
});
