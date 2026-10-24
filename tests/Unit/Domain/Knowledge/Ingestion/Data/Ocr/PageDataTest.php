<?php

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;

/**
 * A page split into parts keeps its page number in every part; its media
 * (images, tables, links) go with the first part only, so they are stored
 * once.
 */
it('gives the media of a split page to its first part only', function (): void {
    $page = new PageData(pageNumber: 7, content: 'Long page', checksum: 'x', imagePaths: ['a.png'], tables: ['| a |'], hyperlinks: ['https://x.test']);

    [$first, $second] = $page->partsOf(['Long', 'page']);

    expect([$first->pageNumber, $second->pageNumber])->toBe([7, 7])
        ->and($first->imagePaths)->toBe(['a.png'])
        ->and($first->tables)->toBe(['| a |'])
        ->and($second->imagePaths)->toBe([])
        ->and($second->tables)->toBe([])
        ->and($first->checksum)->not->toBe($second->checksum)
        ->and($page->partsOf(['Long page']))->toBe([$page]);
});
