<?php

use App\Identity\Workspace\Models\User;
use Domain\Helpdesk\Actions\CreateTicketAction;
use Domain\Helpdesk\Actions\ReplyToTicketAction;
use Domain\Helpdesk\Data\CreateTicketData;
use Domain\Helpdesk\Data\ReplyToTicketData;
use Domain\Helpdesk\Models\Ticket;
use Domain\Helpdesk\Models\TicketReply;

/**
 * Coverage for the Helpdesk Actions (CreateTicketAction / ReplyToTicketAction).
 * The Helpdesk domain shipped with zero tests despite a clear customer-facing
 * surface (ticket submission + replies).
 *
 * CreateTicketAction has a known wart: `assigned_to => 1` is hardcoded — the
 * tests pin the present-day behaviour but the magic number should move to a
 * config or owner-resolution helper in a follow-up. The seeded demo user
 * (ahmad@mayahi.net) is id=1, so this works in the seeded test DB; in production
 * it pins assignment to whoever has user id=1 today.
 */
beforeEach(function (): void {
    // Confirm the assumption rather than letting the test silently rely on it.
    expect(User::find(1))->not->toBeNull('Test DB must have a user with id=1 (seeded demo user) for assigned_to=1 to satisfy the FK.');
});

describe('CreateTicketAction', function (): void {
    it('creates a ticket scoped to the submitter with the requested title, message, and priority', function (): void {
        $user = User::factory()->create();

        $ticket = app(CreateTicketAction::class)->execute(new CreateTicketData(
            user: $user,
            title: 'Cannot upload PDFs',
            message: 'Every upload returns a 422.',
            priority: 'high',
        ));

        expect($ticket)->toBeInstanceOf(Ticket::class)
            ->and($ticket->user_id)->toBe($user->id)
            ->and($ticket->title)->toBe('Cannot upload PDFs')
            ->and($ticket->message)->toBe('Every upload returns a 422.')
            ->and($ticket->priority)->toBe('high');
    });

    it('pins assigned_to to the magic-number default (user id=1)', function (): void {
        // Documents the hardcoded `assigned_to => 1` in the Action. When that
        // magic number is replaced with a config/owner-resolution helper, this
        // assertion is expected to change — that's the signal to update it.
        $user = User::factory()->create();

        $ticket = app(CreateTicketAction::class)->execute(new CreateTicketData(
            user: $user,
            title: 't',
            message: 'm',
            priority: 'medium',
        ));

        expect($ticket->assigned_to)->toBe(1);
    });
});

describe('ReplyToTicketAction', function (): void {
    it('creates a user-typed reply attached to the ticket with the right author + body', function (): void {
        $ticketOwner = User::factory()->create();
        $ticket = app(CreateTicketAction::class)->execute(new CreateTicketData(
            user: $ticketOwner,
            title: 'Need help',
            message: 'Original.',
            priority: 'medium',
        ));

        $replier = User::factory()->create();

        app(ReplyToTicketAction::class)->execute(new ReplyToTicketData(
            ticket: $ticket,
            user: $replier,
            message: 'Thanks for the report — looking into it.',
        ));

        $reply = $ticket->replies()->first();

        expect($reply)->toBeInstanceOf(TicketReply::class)
            ->and($reply->user_id)->toBe($replier->id)
            ->and($reply->type)->toBe('user')
            ->and($reply->message)->toBe('Thanks for the report — looking into it.');
    });

    it('appends each reply rather than replacing — multiple calls accumulate', function (): void {
        $owner = User::factory()->create();
        $ticket = app(CreateTicketAction::class)->execute(new CreateTicketData(
            user: $owner,
            title: 't',
            message: 'm',
            priority: 'medium',
        ));

        app(ReplyToTicketAction::class)->execute(new ReplyToTicketData(
            ticket: $ticket,
            user: $owner,
            message: 'first',
        ));
        app(ReplyToTicketAction::class)->execute(new ReplyToTicketData(
            ticket: $ticket,
            user: $owner,
            message: 'second',
        ));

        $messages = $ticket->replies()->orderBy('id')->pluck('message')->all();

        expect($messages)->toBe(['first', 'second']);
    });
});
