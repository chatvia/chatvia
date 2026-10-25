<?php

use Domain\Conversation\Services\Speech\SpokenText;

/**
 * A reply read aloud: markdown said for the ear (links by their words, no
 * code, pictures or citation markers).
 */
it('says a reply without its markup', function (): void {
    $markdown = <<<'MD'
        # Opening hours

        We open at **9** [1]. See [our page](https://example.com/hours).

        - Monday
        - Tuesday

        ```php
        echo 'skip';
        ```

        ![Bar chart of visits](chart.png)
        MD;

    expect(app(SpokenText::class)->from($markdown))->toBe("Opening hours\n\nWe open at 9. See our page.\n\nMonday\nTuesday");
});
