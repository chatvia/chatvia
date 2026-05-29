<?php

it('renders a prompt blade view to a non-empty string', function (): void {
    expect(prompt('knowledge/system'))->toBeString()->not->toBe('');
});

it('interpolates variables without HTML-escaping the content', function (): void {
    $rendered = prompt('summarization/user', ['content' => '<b>hi</b> & co']);

    // {!! $content !!} keeps the raw text — no &lt;b&gt; / &amp; entities.
    expect($rendered)->toContain('<b>hi</b> & co');
});

it('accepts nested slash-style names', function (): void {
    $rendered = prompt('knowledge/follow-up/user', [
        'retrieved_chunks' => 'CHUNK_ONE',
        'user_query' => 'QUESTION?',
    ]);

    expect($rendered)->toContain('CHUNK_ONE')->toContain('QUESTION?');
});
