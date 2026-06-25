<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Actions\RecordConversationalSubmissionAction;
use Domain\Form\Data\CaptureLeadData;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Models\FormSubmission;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = makeTeamAgent($this->user, 'Bot');
});

it('records a conversational capture as a submission under the default lead form', function (): void {
    Event::fake([FormSubmitted::class]);

    $submission = app(RecordConversationalSubmissionAction::class)->execute(
        $this->agent,
        null,
        new CaptureLeadData(name: 'Jane', email: 'jane@acme.com', source: 'tool'),
    );

    expect($submission->status)->toBe(FormSubmission::STATUS_SUBMITTED)
        ->and($submission->email)->toBe('jane@acme.com')  // accessor over decrypted values
        ->and($submission->name)->toBe('Jane')
        ->and($submission->definition->slug)->toBe('lead-capture');

    Event::assertDispatchedTimes(FormSubmitted::class, 1);
});

it('de-dupes an identical contact captured again (no second row, no second event)', function (): void {
    Event::fake([FormSubmitted::class]);
    $data = new CaptureLeadData(name: 'Jane', email: 'jane@acme.com', source: 'tool');

    $first = app(RecordConversationalSubmissionAction::class)->execute($this->agent, null, $data);
    $second = app(RecordConversationalSubmissionAction::class)->execute($this->agent, null, $data);

    expect($second->id)->toBe($first->id)
        ->and(FormSubmission::query()->where('agent_id', $this->agent->id)->count())->toBe(1);

    Event::assertDispatchedTimes(FormSubmitted::class, 1); // only the first capture
});

it('stores a new submission when the captured details differ', function (): void {
    $a = app(RecordConversationalSubmissionAction::class)->execute($this->agent, null, new CaptureLeadData(name: 'Jane', email: 'jane@acme.com'));
    $b = app(RecordConversationalSubmissionAction::class)->execute($this->agent, null, new CaptureLeadData(name: 'John', email: 'john@acme.com'));

    expect($b->id)->not->toBe($a->id)
        ->and(FormSubmission::query()->where('agent_id', $this->agent->id)->count())->toBe(2);
});
