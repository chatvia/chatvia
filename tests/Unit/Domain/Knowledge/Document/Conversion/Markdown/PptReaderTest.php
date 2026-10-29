<?php

use Domain\Knowledge\Document\Conversion\Markdown\PptReader;
use Illuminate\Support\Facades\File;
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

it('reads the version of each slide the last save left, not an earlier one', function (): void {
    $path = new PowerPointBinaryFile()
        ->slide(['Agenda'], ['Updated plan'])
        ->earlierSaveOfFirstSlide(['Old plan'])
        ->save();

    expect(pptrSlides($path))->toBe(["Agenda\nUpdated plan"]);
});
