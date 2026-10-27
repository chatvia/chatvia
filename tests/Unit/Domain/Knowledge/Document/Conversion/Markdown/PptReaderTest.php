<?php

use Domain\Knowledge\Document\Conversion\Markdown\PptReader;
use Illuminate\Support\Facades\File;
use Tests\Support\Knowledge\Document\OldOffice\CompoundFileWriter;
use Tests\Support\Knowledge\Document\OldOffice\PowerPointBinaryFile;

/**
 * The built-in reader of old PowerPoint files (.ppt). The files are built by
 * PowerPointBinaryFile, which stores the slides out of show order, keeps
 * master-slide text, and can leave an older version of a slide behind.
 */
function pptrSlides(string $path): array
{
    try {
        return new PptReader()->slides($path);
    } finally {
        File::delete($path);
    }
}

it('reads the slides in show order, placeholders and text boxes in shape order', function (): void {
    $path = new PowerPointBinaryFile()
        ->slide(['Welcome', "First point\rSecond point"], ['A text box', '*'])
        ->slide(['Prices'])
        ->save();

    expect(pptrSlides($path))->toBe([
        "Welcome\nFirst point\nSecond point\nA text box",
        'Prices',
    ]);
});

it('reads Unicode and one-byte text, and leaves out the master slide', function (): void {
    $path = new PowerPointBinaryFile()->slide(['Café menu'], ['日本語のテキスト', 'Привет'])->save();
    $slides = pptrSlides($path);

    expect($slides)->toBe(["Café menu\n日本語のテキスト\nПривет"])
        ->and(implode("\n", $slides))->not->toContain('Master');
});

it('reads the version of each slide the last save left, not an earlier one', function (): void {
    $path = new PowerPointBinaryFile()
        ->slide(['Agenda'], ['Updated plan'])
        ->earlierSaveOfFirstSlide(['Old plan'])
        ->save();

    expect(pptrSlides($path))->toBe(["Agenda\nUpdated plan"]);
});

it('gives all slides as Markdown, a blank line apart', function (): void {
    $path = deleteAfterTest(new PowerPointBinaryFile()->slide(['One'])->slide(['Two'])->save());

    expect(new PptReader()->toMarkdown($path))->toBe("One\n\nTwo");
});

it('refuses a password-protected file and a file that is not a presentation', function (): void {
    $locked = new PowerPointBinaryFile()->slide(['Secret'])->encrypted()->save();
    $other = tempTestFile('other.ppt', CompoundFileWriter::write(['WordDocument' => 'not a presentation']));

    expect(fn (): array => pptrSlides($locked))->toThrow(RuntimeException::class, 'password protected')
        ->and(fn (): array => pptrSlides($other))->toThrow(RuntimeException::class, 'not a PowerPoint presentation');
});
