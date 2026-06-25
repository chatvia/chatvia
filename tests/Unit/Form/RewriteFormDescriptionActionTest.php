<?php

use Domain\Form\Actions\RewriteFormDescriptionAction;

it('returns the rewritten instruction from the model', function (): void {
    fakeLlmCompletion('Show this form when the visitor asks to book a demo or speak with sales.');

    $result = app(RewriteFormDescriptionAction::class)->execute(
        'whenever asked to book a demo',
        'Book a demo',
        'lead',
    );

    expect($result)->toBe('Show this form when the visitor asks to book a demo or speak with sales.');
});

it('strips surrounding quotes the model adds', function (): void {
    fakeLlmCompletion('  "Show this form when the visitor requests a quote."  ');

    $result = app(RewriteFormDescriptionAction::class)->execute('quote', 'Get a quote', 'lead');

    expect($result)->toBe('Show this form when the visitor requests a quote.');
});

it('works from an empty draft using the name and type', function (): void {
    fakeLlmCompletion('Show this form when the visitor wants to schedule a booking.');

    $result = app(RewriteFormDescriptionAction::class)->execute(null, 'Booking', 'booking');

    expect($result)->toBe('Show this form when the visitor wants to schedule a booking.');
});
