<?php

/**
 * Covers the extension-based OCR helpers that replaced the MIME-only gates.
 * MIME is unreliable for the exotic text formats (.tex/.bib → text/plain,
 * .fb2 → xml, .ipynb → json), so validation/routing/metering key off the
 * file extension — these helpers are that source of truth.
 */
it('reads the lowercased extension from a path or filename', function (): void {
    expect(file_extension('/tmp/Report.PDF'))->toBe('pdf')
        ->and(file_extension('deck.PPTX'))->toBe('pptx')
        ->and(file_extension('/var/x/paper.tex'))->toBe('tex')
        ->and(file_extension('noextension'))->toBe('');
});

it('treats every configured OCR extension as supported', function (): void {
    foreach (ocr_supported_extensions() as $ext) {
        expect(is_ocr_supported('file.'.$ext))->toBeTrue("$ext should be supported");
    }

    expect(is_ocr_supported('malware.exe'))->toBeFalse()
        ->and(is_ocr_supported('archive.zip'))->toBeFalse();
});

it('detects image files by extension only', function (): void {
    expect(is_image_file('photo.png'))->toBeTrue()
        ->and(is_image_file('scan.HEIC'))->toBeTrue()
        ->and(is_image_file('frame.avif'))->toBeTrue()
        ->and(is_image_file('doc.pdf'))->toBeFalse()
        ->and(is_image_file('book.epub'))->toBeFalse();
});

it('maps extensions to the metering category stored on filetype', function (): void {
    expect(file_category('a.pdf'))->toBe('pdf')
        ->and(file_category('a.docx'))->toBe('word')
        ->and(file_category('a.odt'))->toBe('word')
        ->and(file_category('a.pptx'))->toBe('presentation')
        ->and(file_category('a.xlsx'))->toBe('spreadsheet')
        ->and(file_category('a.csv'))->toBe('spreadsheet')
        ->and(file_category('a.txt'))->toBe('text')
        ->and(file_category('a.md'))->toBe('markdown')
        ->and(file_category('a.png'))->toBe('image')
        ->and(file_category('a.heic'))->toBe('image')
        ->and(file_category('a.epub'))->toBe('document')
        ->and(file_category('a.tex'))->toBe('document')
        ->and(file_category('a.ipynb'))->toBe('document');
});

it('keeps every non-audio/video category inside the billable pages bucket', function (): void {
    // Mirror of WorkspaceConsumption::pages() — every category file_category()
    // can return for an OCR file must be metered, or uploads slip through free.
    $pagesBucket = ['pdf', 'word', 'markdown', 'text', 'presentation', 'spreadsheet', 'image', 'document'];

    $categories = collect(ocr_supported_extensions())
        ->map(fn (string $ext) => file_category('x.'.$ext))
        ->unique()
        ->values();

    expect($categories->diff($pagesBucket))->toBeEmpty(
        'Some OCR categories are not counted by WorkspaceConsumption::pages()'
    );
});
