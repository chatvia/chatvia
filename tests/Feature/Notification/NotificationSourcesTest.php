<?php

use App\Identity\Workspace\Actions\MessageLimits\RecordMessageUsageAction;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\NewDeviceSignInNotification;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\RequestHandoffAction;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Evaluation\Actions\FinalizeEvalRunAction;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Models\EvalRun;
use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Models\FormDefinition;
use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Knowledge\Ingestion\Actions\Pipeline\CompleteSourceIngestionAction;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Ingestion\Actions\Website\CompleteWebsiteIngestionAction;
use Domain\Knowledge\Ingestion\Enums\TrainingTrigger;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Models\UserNotification;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * Each event that feeds the notification center reaches the right people
 * (the queue is synchronous in tests, so the queued listeners run inline).
 */

beforeEach(function (): void {
    Storage::fake();
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['name' => 'Support']);
});

/** @return list<int> */
function nsRecipients(NotificationKind $kind): array
{
    return UserNotification::query()->where('kind', $kind)->orderBy('user_id')->pluck('user_id')->all();
}

function nsConversation(Agent $agent): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true]);
}

it('tells the people who can take over that a visitor is waiting, and clears it once someone does', function (): void {
    $takesOver = teammateWithPermissions($this->owner, ['agent.chat.view', 'agent.chat.takeover']);
    teammateWithPermissions($this->owner, ['agent.chat.view']);
    $conversation = nsConversation($this->agent);

    app(RequestHandoffAction::class)->execute($conversation);
    // Asking again while waiting changes nothing.
    app(RequestHandoffAction::class)->execute($conversation->fresh());

    expect(nsRecipients(NotificationKind::HandoffRequested))->toBe([$this->owner->id, $takesOver->id]);

    $notification = UserNotification::query()->where('user_id', $takesOver->id)->sole();
    expect($notification->content->url)->toContain('conversation_id='.$conversation->external_id)
        ->and($notification->content->agent->name)->toBe('Support');

    HandedOff::dispatch($conversation->external_id, HandoffStatus::Human->value);

    expect(UserNotification::query()->unread()->count())->toBe(0);
});

it('gathers finished training per agent and keeps each failure apart', function (): void {
    Bus::fake([SummaryIngestionJob::class]);
    $trainer = teammateWithPermissions($this->owner, ['agent.sources.train.files']);
    teammateWithPermissions($this->owner, ['agent.sources.train.web']);
    [$first, $second, $broken] = AgentFileFactory::new()->count(3)->create(['workspace_id' => $this->agent->workspace_id])->all();

    app(CompleteSourceIngestionAction::class)->execute($first, $this->agent->external_id);
    app(CompleteSourceIngestionAction::class)->execute($second, $this->agent->external_id);
    app(MarkSourceFailedAction::class)->execute($broken, $this->agent, new RuntimeException('Unreadable PDF'));

    expect(nsRecipients(NotificationKind::SourceTrained))->toBe([$this->owner->id, $trainer->id]);

    $trained = UserNotification::query()->where('user_id', $trainer->id)->where('kind', NotificationKind::SourceTrained)->sole();
    expect($trained->count)->toBe(2)
        ->and($trained->content->subject)->toBe($second->filename)
        ->and($trained->content->url)->toEndWith('/sources/files');

    $failed = UserNotification::query()->where('user_id', $trainer->id)->where('kind', NotificationKind::SourceTrainingFailed)->sole();
    expect($failed->content->subject)->toBe($broken->filename)
        ->and($failed->content->subject)->not->toContain('Unreadable');
});

it('stays quiet when a scheduled website refresh succeeds, but not when a person retrains', function (): void {
    Bus::fake([SummaryIngestionJob::class]);
    $website = SourceWebsite::query()->create([
        'workspace_id' => $this->agent->workspace_id,
        'url' => 'https://docs.example.test/'.Str::lower(Str::random(6)),
        'status' => 'ingesting',
    ]);
    $this->agent->websites()->attach($website->id);

    app(CompleteWebsiteIngestionAction::class)->execute($website, $this->agent, TrainingTrigger::Scheduled);
    expect(UserNotification::query()->count())->toBe(0);

    app(CompleteWebsiteIngestionAction::class)->execute($website->fresh(), $this->agent);
    expect(nsRecipients(NotificationKind::SourceTrained))->toBe([$this->owner->id])
        ->and(UserNotification::query()->sole()->content->detail)->toBe('website');
});

it('tells the people who see leads about a new one, naming the form only', function (): void {
    $leadReader = teammateWithPermissions($this->owner, ['agent.leads.view']);
    teammateWithPermissions($this->owner, ['agent.chat.view']);
    $form = FormDefinition::factory()->forAgent($this->agent)->create(['name' => 'Book a demo']);

    FormSubmitted::dispatch(new CapturedSubmissionData(
        agentId: $this->agent->id,
        workspaceId: $this->agent->workspace_id,
        submissionId: 1,
        formSlug: $form->slug,
        formType: $form->form_type,
        conversationExternalId: null,
        created: true,
        source: 'form',
        values: ['email' => 'visitor@example.test'],
    ));

    expect(nsRecipients(NotificationKind::LeadCaptured))->toBe([$this->owner->id, $leadReader->id]);

    $content = UserNotification::query()->where('user_id', $leadReader->id)->sole()->content;
    expect($content->subject)->toBe('Book a demo')
        ->and(json_encode($content->toArray()))->not->toContain('visitor@example.test');
});

it('gathers blocked attacks per agent for the people who see its guardrails', function (): void {
    $guard = teammateWithPermissions($this->owner, ['agent.guardrails.view']);

    foreach (range(1, 3) as $attempt) {
        GuardrailTriggered::dispatch(
            $this->agent->id, $this->agent->name, $this->agent->workspace_id, null, null, null, null, null,
            'input', 'prompt_injection', 'blocked', 'high', [], 'ignore all previous instructions',
        );
    }

    $attack = UserNotification::query()->where('user_id', $guard->id)->sole();
    expect($attack->kind)->toBe(NotificationKind::AttackBlocked)
        ->and($attack->count)->toBe(3)
        ->and($attack->content->detail)->toBe('prompt_injection')
        ->and(json_encode($attack->content->toArray()))->not->toContain('ignore all previous');
});

it('tells the security team about a new API token, but not the person who made it', function (): void {
    $security = teammateWithPermissions($this->owner, ['account.security']);

    event(SecurityEventOccurred::for(
        SecurityAuditAction::ApiKeyCreated,
        $this->agent->workspace_id,
        SecurityActorData::forUser($this->owner),
        targetLabel: 'Zapier',
    ));
    event(SecurityEventOccurred::for(
        SecurityAuditAction::AuthLogin,
        $this->agent->workspace_id,
        SecurityActorData::forUser($this->owner),
    ));

    expect(nsRecipients(NotificationKind::SecuritySettingsChanged))->toBe([$security->id]);

    $content = UserNotification::query()->sole()->content;
    expect($content->detail)->toBe('api_key.created')
        ->and($content->actor)->toBe($this->owner->name)
        ->and($content->subject)->toBe('Zapier');
});

it('tells the owner once at 80% of the monthly messages and once at the limit', function (): void {
    $limit = MessageLimit::create([
        'workspace_id' => $this->agent->workspace_id,
        'monthly_limit' => 5,
        'messages_used' => 2,
        'period_starts_at' => now()->startOfMonth(),
    ]);

    foreach (range(1, 4) as $message) {
        app(RecordMessageUsageAction::class)->execute($limit->fresh());
    }

    expect(nsRecipients(NotificationKind::MessageLimitWarning))->toBe([$this->owner->id])
        ->and(nsRecipients(NotificationKind::MessageLimitReached))->toBe([$this->owner->id])
        ->and(UserNotification::query()->count())->toBe(2);
});

it('tells the people who manage protection about a rate limit at most once in ten minutes', function (): void {
    AgentRateLimitExceeded::dispatch($this->agent, 10);
    AgentRateLimitExceeded::dispatch($this->agent, 10);

    expect(nsRecipients(NotificationKind::RateLimitExceeded))->toBe([$this->owner->id])
        ->and(UserNotification::query()->sole()->content->detail)->toBe('10');
});

it('tells the person who started an eval run that it finished', function (): void {
    $evaluator = teammateWithPermissions($this->owner, ['agent.evals.view']);
    $run = EvalRun::create([
        'agent_id' => $this->agent->id,
        'agent_name' => $this->agent->name,
        'workspace_id' => $this->agent->workspace_id,
        'status' => EvalRunStatus::Running,
        'total_cases' => 0,
        'started_at' => now(),
        'triggered_by_user_id' => $evaluator->id,
    ]);

    (new FinalizeEvalRunAction)->execute($run->fresh());

    expect(nsRecipients(NotificationKind::EvalRunFinished))->toBe([$evaluator->id])
        ->and(UserNotification::query()->sole()->content->detail)->toBe('completed');
});

it('puts a new-device sign-in in the person\'s own notifications, naming the browser', function (): void {
    $this->owner->notify(new NewDeviceSignInNotification(
        '203.0.113.9',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36',
        now(),
    ));

    $notification = UserNotification::query()->sole();
    expect($notification->user_id)->toBe($this->owner->id)
        ->and($notification->kind)->toBe(NotificationKind::NewDeviceSignIn)
        ->and($notification->content->subject)->toContain('Chrome');
});

it('leaves a category out of the bell when the person switched it off', function (): void {
    $this->owner->update(['notification_preferences' => ['usage' => ['bell' => false, 'desktop' => false, 'sound' => false]]]);

    AgentRateLimitExceeded::dispatch($this->agent, 10);

    expect(UserNotification::query()->count())->toBe(0);
});
