<?php

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Helpdesk\Actions\CreateTicketAction;
use Domain\Helpdesk\Data\CreateTicketData;
use Domain\Helpdesk\Models\Ticket;

/*
 * Coverage for the Helpdesk Actions (CreateTicketAction). The Helpdesk domain
 * shipped with zero tests despite a clear customer-facing surface (ticket
 * submission). Ticket replies are a trivial inline write in
 * ReplyToTicketController, covered at the HTTP level.
 *
 * Assignment: `helpdesk.default_assignee_id` wins when configured; otherwise
 * the ticket falls back to an Admin-type user (the old hardcoded
 * `assigned_to => 1` is gone). The tests create their own admin instead of
 * relying on any seeded user.
 */
beforeEach(function (): void {
    // The admin fallback needs an Admin-type user to exist, or the FK on
    // tickets.assigned_to has nothing to point at.
    $this->admin = User::factory()->admin()->create();
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

    it('assigns the ticket to the configured default assignee when one is set', function (): void {
        $assignee = User::factory()->create();
        config(['helpdesk.default_assignee_id' => $assignee->id]);

        $ticket = app(CreateTicketAction::class)->execute(new CreateTicketData(
            user: User::factory()->create(),
            title: 't',
            message: 'm',
            priority: 'medium',
        ));

        expect($ticket->assigned_to)->toBe($assignee->id);
    });

    it('falls back to an admin user when no default assignee is configured', function (): void {
        config(['helpdesk.default_assignee_id' => null]);

        $ticket = app(CreateTicketAction::class)->execute(new CreateTicketData(
            user: User::factory()->create(),
            title: 't',
            message: 'm',
            priority: 'medium',
        ));

        $assignee = User::find($ticket->assigned_to);

        expect($assignee)->not->toBeNull()
            ->and($assignee->isAdmin())->toBeTrue();
    });
});

describe('CreateTicketAction assignee fallbacks', function (): void {
    it('leaves the ticket unassigned instead of writing a 0 foreign key when no admin exists', function (): void {
        config(['helpdesk.default_assignee_id' => null]);
        $this->admin->delete();
        User::query()->where('type', UserType::Admin)->delete();

        $ticket = app(CreateTicketAction::class)->execute(new CreateTicketData(
            user: User::factory()->create(),
            title: 't',
            message: 'm',
            priority: 'low',
        ));

        expect($ticket->fresh()->assigned_to)->toBeNull();
    });

    it('accepts the assignee id as a numeric string, as it arrives from the environment', function (): void {
        $assignee = User::factory()->create();
        config(['helpdesk.default_assignee_id' => (string) $assignee->id]);

        $ticket = app(CreateTicketAction::class)->execute(new CreateTicketData(
            user: User::factory()->create(),
            title: 't',
            message: 'm',
            priority: 'low',
        ));

        expect($ticket->assigned_to)->toBe($assignee->id)
            ->and($ticket->assignedTo->is($assignee))->toBeTrue();
    });

    it('falls back to an admin when the configured assignee no longer exists', function (): void {
        config(['helpdesk.default_assignee_id' => 999_999_999]);

        $ticket = app(CreateTicketAction::class)->execute(new CreateTicketData(
            user: User::factory()->create(),
            title: 't',
            message: 'm',
            priority: 'low',
        ));

        expect(User::find($ticket->assigned_to)?->isAdmin())->toBeTrue();
    });

    it('ships a helpdesk config file so the setting is readable at all', function (): void {
        expect(config()->has('helpdesk.default_assignee_id'))->toBeTrue();
    });
});
