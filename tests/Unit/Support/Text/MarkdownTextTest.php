<?php

use Support\Text\MarkdownText;

/**
 * A passage shown to a person (the citation card) reads as plain text: a
 * heading becomes its own sentence, a link keeps its text, and the marks of
 * images, emphasis, code, quotes, lists and tables go. A plain `*` stays.
 */
it('turns Markdown into one line of readable text', function (): void {
    $markdown = "# Créez des agents IA\n\n## Questions fréquemment posées?\nTout ce que vous **devez** savoir: voir [le guide](https://x.test) ![logo](a.png) et `code`.\n\n- un\n1. deux\n\n| A | B |\n|---|---|\n| 1 | 2 |\n\n> Note: 2 * 3 = 6";

    expect(MarkdownText::plain($markdown))->toBe('Créez des agents IA. Questions fréquemment posées? Tout ce que vous devez savoir: voir le guide et code. un deux A B 1 2 Note: 2 * 3 = 6');
});
