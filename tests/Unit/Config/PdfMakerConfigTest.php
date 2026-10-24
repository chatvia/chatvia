<?php

/**
 * The PDF maker renders HTML built from chat transcripts. WeasyPrint may load
 * only `data:` URLs while it does, so a link or image in a message can never
 * make it read a local file or call out to the network.
 */
it('lets WeasyPrint load only data: URLs, never files or the network', function (): void {
    expect(config('laravel-pdf.weasyprint.allowed-protocols'))->toBe('data');
});
