<?php

use App\Admin\Issue\Enums\IssueActorKind;
use App\Admin\Issue\Enums\IssueStatus;
use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Models\IssueOccurrence;
use App\Admin\Resources\Issues\IssueResource;
use App\Admin\Resources\Issues\Pages\ListIssues;
use App\Admin\Resources\Issues\Pages\ViewIssue;
use App\Admin\Resources\Issues\RelationManagers\OccurrencesRelationManager;
use App\Identity\Workspace\Models\User;
use Livewire\Livewire;

/**
 * The admin panel's Issues page: open issues in the list, one issue's
 * advice and the person it happened to, and resolving it.
 */
it('lists the open issues and counts them in the sidebar', function (): void {
    $open = Issue::factory()->create();
    $resolved = Issue::factory()->create(['status' => IssueStatus::Resolved]);

    adminPage(ListIssues::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$resolved]);

    expect(IssueResource::getNavigationBadge())->toBe('1');
});

it('shows an issue with its hint and the person it happened to, and resolves it', function (): void {
    $issue = Issue::factory()->create();
    $user = User::factory()->create(['name' => 'Jane Doe']);
    IssueOccurrence::factory()->create(['issue_id' => $issue->id, 'actor_kind' => IssueActorKind::Staff, 'user_id' => $user->id]);

    $admin = adminUser();

    Livewire::actingAs($admin, 'admin')
        ->test(OccurrencesRelationManager::class, ['ownerRecord' => $issue, 'pageClass' => ViewIssue::class])
        ->assertSee('Jane Doe (Staff)');

    Livewire::actingAs($admin, 'admin')
        ->test(ViewIssue::class, ['record' => $issue->getRouteKey()])
        ->assertSee('fal.ai refused the API key')
        ->assertSee('Check the fal.ai API key')
        ->callAction('resolve');

    expect($issue->fresh()?->status)->toBe(IssueStatus::Resolved);
});
