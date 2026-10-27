<?php

use Domain\Form\Actions\RewriteFormDescriptionAction;
use Domain\Form\Data\RewriteFormDescriptionData;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\DomainValidationException;

/**
 * The form editor's "Rewrite" button: the model turns the owner's note on
 * when to show a form into a clear instruction for the agent. The model is
 * faked at its HTTP call.
 */
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

it('gives the model room to reason before it writes the instruction', function (): void {
    fakeLlmCompletion('Show this form when the visitor asks for a quote.');

    app(RewriteFormDescriptionAction::class)->execute(
        new RewriteFormDescriptionData(agent_instructions: 'quote', name: 'Get a quote', form_type: 'lead'),
    );

    Http::assertSent(fn (Request $request): bool => ($request->data()['max_tokens'] ?? $request->data()['max_completion_tokens'] ?? 0) >= 500);
});

it('refuses a blank reply, so the editor keeps the draft', function (): void {
    fakeLlmCompletion('  ""  ');

    app(RewriteFormDescriptionAction::class)->execute(
        new RewriteFormDescriptionData(agent_instructions: 'quote', name: 'Get a quote', form_type: 'lead'),
    );
})->throws(DomainValidationException::class);

it('writes a description from an empty draft using the name and type', function (): void {
    fakeLlmCompletion('Show this form when the visitor wants to schedule a booking.');

    $result = app(RewriteFormDescriptionAction::class)->execute(
        new RewriteFormDescriptionData(agent_instructions: null, name: 'Booking', form_type: 'booking'),
    );

    expect($result)->toBe('Show this form when the visitor wants to schedule a booking.');
});
