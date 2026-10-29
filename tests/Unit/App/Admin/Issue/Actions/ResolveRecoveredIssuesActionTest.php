<?php

use App\Admin\Issue\Actions\ResolveRecoveredIssuesAction;
use App\Admin\Issue\Enums\IssueResolution;
use App\Admin\Issue\Enums\IssueStatus;
use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Services\IssueReporter;
use Illuminate\Support\Facades\Cache;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;

/**
 * A service that works again closes its own open issues, but not a gone
 * model (a working service does not bring it back), an ignored issue,
 * another service's issue or one subject's issue. With a subject only that
 * subject's issues close; with a kind only that kind's.
 */
it('closes the open issues of a service that works again', function (): void {
    Cache::forever(IssueReporter::openKey('fal'), true);
    $refused = Issue::factory()->create();
    $gone = Issue::factory()->create(['kind' => IssueTypeKey::MODEL_GONE, 'subject' => 'fal-ai/old']);
    $ignored = Issue::factory()->create(['kind' => IssueTypeKey::OUT_OF_CREDIT, 'status' => IssueStatus::Ignored]);
    $other = Issue::factory()->create(['service' => 'openai']);

    expect(app(ResolveRecoveredIssuesAction::class)->execute('fal'))->toBe(1)
        ->and($refused->fresh()?->resolution)->toBe(IssueResolution::Recovered)
        ->and($gone->fresh()?->status)->toBe(IssueStatus::Open)
        ->and($ignored->fresh()?->status)->toBe(IssueStatus::Ignored)
        ->and($other->fresh()?->status)->toBe(IssueStatus::Open)
        ->and(Cache::has(IssueReporter::openKey('fal')))->toBeFalse();
});

it('closes only the subject and kind it is told', function (): void {
    $stopped = Issue::factory()->create(['service' => ServiceKey::QUEUE, 'kind' => IssueTypeKey::SERVICE_STOPPED]);
    $jobA = Issue::factory()->create(['service' => ServiceKey::QUEUE, 'kind' => IssueTypeKey::JOB_FAILED, 'subject' => 'App\\Jobs\\A']);
    $jobB = Issue::factory()->create(['service' => ServiceKey::QUEUE, 'kind' => IssueTypeKey::JOB_FAILED, 'subject' => 'App\\Jobs\\B']);
    $messages = Issue::factory()->create(['service' => ServiceKey::WORKSPACES, 'kind' => IssueTypeKey::MESSAGE_LIMIT_REACHED, 'subject' => '5']);
    $generations = Issue::factory()->create(['service' => ServiceKey::WORKSPACES, 'kind' => IssueTypeKey::GENERATION_LIMIT_REACHED, 'subject' => '5']);

    $action = app(ResolveRecoveredIssuesAction::class);
    $action->execute(ServiceKey::QUEUE);
    $action->execute(ServiceKey::QUEUE, 'App\\Jobs\\A');
    $action->execute(ServiceKey::WORKSPACES, '5', IssueTypeKey::MESSAGE_LIMIT_REACHED);

    expect($stopped->fresh()?->status)->toBe(IssueStatus::Resolved)
        ->and($jobA->fresh()?->status)->toBe(IssueStatus::Resolved)
        ->and($jobB->fresh()?->status)->toBe(IssueStatus::Open)
        ->and($messages->fresh()?->status)->toBe(IssueStatus::Resolved)
        ->and($generations->fresh()?->status)->toBe(IssueStatus::Open);
});
