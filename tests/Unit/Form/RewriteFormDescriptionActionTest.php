<?php

use Domain\Form\Actions\RewriteFormDescriptionAction;
use Domain\Form\Data\RewriteFormDescriptionData;

it('returns the rewritten instruction from the model', function (): void {
    fakeLlmCompletion('Show this form when the visitor asks to book a demo or speak with sales.');

    $result = app(RewriteFormDescriptionAction::class)->execute(
        new RewriteFormDescriptionData(
            agent_instructions: 'whenever asked to book a demo',
            name: 'Book a demo',
            form_type: 'lead',
        ),
    );

    expect($result)->toBe('Show this form when the visitor asks to book a demo or speak with sales.');
});

it('strips surrounding quotes the model adds', function (): void {
    fakeLlmCompletion('  "Show this form when the visitor requests a quote."  ');

    $result = app(RewriteFormDescriptionAction::class)->execute(
        new RewriteFormDescriptionData(agent_instructions: 'quote', name: 'Get a quote', form_type: 'lead'),
    );

    expect($result)->toBe('Show this form when the visitor requests a quote.');
});

it('works from an empty draft using the name and type', function (): void {
    fakeLlmCompletion('Show this form when the visitor wants to schedule a booking.');

    $result = app(RewriteFormDescriptionAction::class)->execute(
        new RewriteFormDescriptionData(agent_instructions: null, name: 'Booking', form_type: 'booking'),
    );

    expect($result)->toBe('Show this form when the visitor wants to schedule a booking.');
});
