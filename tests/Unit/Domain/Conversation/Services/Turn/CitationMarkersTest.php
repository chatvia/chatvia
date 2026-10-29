<?php

use Domain\Conversation\Services\Turn\CitationMarkers;

/**
 * An answer's citation markers are made regular before it is stored:
 * `[1, 3]` becomes `[1][3]`, a marker for a block the model was not given is
 * removed, and code and markdown links are left alone. A spoken answer loses
 * every marker.
 */
it('makes markers regular and drops the ones for blocks the model was not given', function (): void {
    $markers = new CitationMarkers;

    expect($markers->normalize('Refunds take five days [1, 3]. Shipping is free [9].', 3))->toBe('Refunds take five days[1][3]. Shipping is free.')
        ->and($markers->normalize('See [the guide](https://x.test/1) and `arr[1]`.', 3))->toBe('See [the guide](https://x.test/1) and `arr[1]`.')
        ->and($markers->normalize("```\n\$a[2] = 1;\n```\nDone [2].", 2))->toBe("```\n\$a[2] = 1;\n```\nDone[2].");
});

it('strips every marker from a spoken answer', function (): void {
    expect((new CitationMarkers)->strip('It takes five days [1][2], or one [3, 4].'))->toBe('It takes five days, or one.');
});
