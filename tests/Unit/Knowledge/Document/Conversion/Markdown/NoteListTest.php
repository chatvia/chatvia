<?php

use Domain\Knowledge\Document\Conversion\Markdown\NoteList;

/**
 * Footnotes numbered in the order they are referenced.
 */
it('numbers notes in order and lists them at the end', function (): void {
    $notes = new NoteList;

    expect($notes->toMarkdown())->toBe('')
        ->and($notes->add("  First\n note. "))->toBe('[^1]')
        ->and($notes->add('Second.'))->toBe('[^2]')
        ->and($notes->toMarkdown())->toBe("[^1]: First note.\n\n[^2]: Second.");
});
