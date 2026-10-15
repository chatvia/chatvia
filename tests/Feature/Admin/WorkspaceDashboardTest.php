<?php

use App\Admin\Pages\Dashboard;
use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Admin\Resources\Workspaces\RelationManagers\AgentsRelationManager;
use App\Admin\Resources\Workspaces\RelationManagers\TeammatesRelationManager;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use App\Admin\Widgets\WorkspaceStatsOverview;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeleteAccountAction;
use App\Identity\Workspace\Jobs\DeleteWorkspaceJob;
use App\Identity\Workspace\Jobs\PrepareDataExportJob;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Conversation\Enums\ConversationSource;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Support\Localization\Country\Models\Country;

/**
 * The admin panel's page for one workspace: its account, this month's
 * numbers, its agents and teammates, and what staff may do to it (edit,
 * reset the owner's password, deactivate, reactivate, delete, turn off
 * two-step sign-in, export).
 */
function wdRollup(Agent $agent, string $bucketHour, array $values = []): void
{
    DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_hourly_rollups')
        ->insert(array_merge([
            'agent_id' => $agent->id,
            'workspace_id' => $agent->workspace_id,
            'bucket_hour' => $bucketHour,
            'country_code' => '--',
            'llm_external_name' => '',
        ], $values));
}

beforeEach(function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->owner = User::factory()->create([
        'name' => 'Dana Owner',
        'email' => 'dana@acme.test',
        'locale' => 'de',
        'registered_country_code' => 'de',
        'registered_ip' => '203.0.113.9',
    ]);
    $this->workspace = $this->owner->workspace();
    $this->workspace->update(['name' => 'Acme Corp']);
});

function viewWorkspace(): Testable
{
    return Livewire::actingAs(test()->admin, 'admin')
        ->test(ViewWorkspace::class, ['record' => test()->workspace->getRouteKey()]);
}

it('shows the owner\'s account details on the overview', function (): void {
    Country::firstOrCreate(['code' => 'de'], ['name' => 'Germany']);
    Chatter::factory()->ofWorkspace($this->owner)->count(2)->create();

    $this->actingAs($this->admin, 'admin')
        ->get(WorkspaceResource::getUrl('view', ['record' => $this->workspace]))
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertSee('dana@acme.test')
        ->assertSee('German (Deutsch)')
        ->assertSee('Germany (DE)')
        ->assertSee('203.0.113.9')
        ->assertSee('Active')
        ->assertSeeInOrder(['Overview', 'Analytics', 'Agents', 'Teammates']);
});

it('opens on the Overview tab, with Analytics next to it', function (): void {
    $page = viewWorkspace()->assertSet('activeRelationManager', null);

    expect(array_keys($page->instance()->getTabComponents()))->toBe(['', 'analytics', 0, 1]);
});

it('shows this month\'s numbers, the limit and the tool calls', function (): void {
    $agent = createAgent($this->owner);
    wdRollup($agent, '2031-03-10 10:00:00', ['user_messages' => 600, 'assistant_messages' => 634, 'conversations_started' => 77]);
    // Last month: not counted.
    wdRollup($agent, '2031-02-10 10:00:00', ['user_messages' => 5, 'assistant_messages' => 5]);

    $analytics = DB::connection(config('chatvia.analytics.connection'));
    $analytics->table('analytics_agent_function_daily')->insert([
        'agent_id' => $agent->id, 'workspace_id' => $agent->workspace_id, 'bucket_day' => '2031-03-11', 'function_id' => 1, 'invocation_count' => 4,
    ]);
    $analytics->table('analytics_agent_mcp_tool_daily')->insert([
        'agent_id' => $agent->id, 'workspace_id' => $agent->workspace_id, 'bucket_day' => '2031-03-12', 'server_slug' => 'charts', 'tool_name' => 'bar', 'invocation_count' => 3,
    ]);
    $analytics->table('analytics_agent_mcp_tool_daily')->insert([
        'agent_id' => $agent->id, 'workspace_id' => $agent->workspace_id, 'bucket_day' => '2031-02-12', 'server_slug' => 'charts', 'tool_name' => 'bar', 'invocation_count' => 50,
    ]);

    MessageLimit::create([
        'workspace_id' => $this->workspace->id,
        'monthly_limit' => 1000,
        'messages_used' => 250,
        'period_starts_at' => now()->startOfMonth(),
    ]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(WorkspaceStatsOverview::class, ['record' => $this->workspace])
        ->assertSee('1,234')
        ->assertSee('250 / 1,000')
        ->assertSee('25% of agent replies used, restarts on 1 April')
        ->assertSee('Tool calls')
        ->assertSeeInOrder(['Tool calls', '7']);
});

it('says when a workspace has no message limit', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(WorkspaceStatsOverview::class, ['record' => $this->workspace])
        ->assertSee('No limit');
});

it('lists the workspace\'s agents with model, channels, knowledge and traffic', function (): void {
    $agent = createAgent($this->owner, ['name' => 'Acme Helper', 'is_public' => true, 'llm_id' => testLlm(attributes: ['descriptive_name' => 'Qwen3 Test'])->id]);
    $agent->setSetting(ConversationSource::Bar->value, false, 'surfaces');
    AgentTelegramBot::create([
        'agent_id' => $agent->id,
        'bot_token' => '123:token',
        'bot_username' => 'acme_bot',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
    DB::table('agent_sources')->insert([
        ['agent_id' => $agent->id, 'sourceable_type' => 'source_text', 'sourceable_id' => 1],
        ['agent_id' => $agent->id, 'sourceable_type' => 'source_text', 'sourceable_id' => 2],
    ]);
    wdRollup($agent, '2031-03-18 09:00:00', ['user_messages' => 10, 'assistant_messages' => 12, 'conversations_started' => 3]);

    $quiet = createAgent($this->owner, ['name' => 'Quiet Bot']);
    $elsewhere = createAgent(User::factory()->create(), ['name' => 'Other Bot']);

    Livewire::actingAs($this->admin, 'admin')
        ->test(AgentsRelationManager::class, ['ownerRecord' => $this->workspace, 'pageClass' => ViewWorkspace::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$agent, $quiet])
        ->assertCanNotSeeTableRecords([$elsewhere])
        ->assertTableColumnStateSet('llm.descriptive_name', 'Qwen3 Test', $agent)
        ->assertTableColumnStateSet('answers_on', ['Hosted Chat', 'Chat Widget', 'Telegram'], $agent)
        ->assertTableColumnStateSet('sources_count', 2, $agent)
        ->assertTableColumnStateSet('messages_this_month', 22, $agent)
        ->assertTableColumnStateSet('conversations_this_month', 3, $agent)
        ->assertTableColumnStateSet('messages_this_month', 0, $quiet)
        ->assertTableColumnStateSet('last_active_at', null, $quiet)
        ->assertSee('Public');
});

it('lists the owner first, then teammates with their team roles', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.view']);
    $teammate->update(['name' => 'Aaron Teammate']);
    $teamRole = DB::table('team_user')
        ->join('teams', 'teams.id', '=', 'team_user.team_id')
        ->join('roles', 'roles.id', '=', 'team_user.role_id')
        ->where('team_user.user_id', $teammate->id)
        ->first(['teams.name as team', 'roles.name as role']);

    Livewire::actingAs($this->admin, 'admin')
        ->test(TeammatesRelationManager::class, ['ownerRecord' => $this->workspace, 'pageClass' => ViewWorkspace::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->owner, $teammate], inOrder: true)
        ->assertTableColumnStateSet('workspace_role', 'Owner', $this->owner)
        ->assertTableColumnStateSet('workspace_role', 'Teammate', $teammate)
        ->assertTableColumnStateSet('teams', ["{$teamRole->team} · {$teamRole->role}"], $teammate)
        ->assertTableColumnStateSet('teams', ['Every team, as owner'], $this->owner);
});

it('deactivates the workspace with a reason and reactivates it', function (): void {
    $teammate = User::factory()->employee($this->owner)->create();
    $chatter = Chatter::factory()->ofWorkspace($this->owner)->create();

    viewWorkspace()
        ->assertActionHidden('reactivate')
        ->callAction('deactivate', data: ['reason' => 'Unpaid invoice'])
        ->assertHasNoActionErrors()
        ->assertActionHidden('deactivate')
        ->assertActionVisible('reactivate');

    $activation = app(WorkspaceActivation::class);
    $workspaceId = $this->owner->workspace()->id;

    expect($this->owner->fresh()->deactivation_reason)->toBe('Unpaid invoice')
        ->and($teammate->fresh()->isDeactivated())->toBeTrue()
        ->and($chatter->fresh()->deactivated_at)->not->toBeNull()
        ->and($activation->isActive($workspaceId))->toBeFalse();

    viewWorkspace()
        ->callAction('reactivate')
        ->assertHasNoActionErrors();

    expect($this->owner->fresh()->isDeactivated())->toBeFalse()
        ->and($teammate->fresh()->isDeactivated())->toBeFalse()
        ->and($chatter->fresh()->deactivated_at)->toBeNull()
        ->and($activation->isActive($workspaceId))->toBeTrue();
});

it('needs a reason to deactivate', function (): void {
    viewWorkspace()
        ->callAction('deactivate', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($this->owner->fresh()->isDeactivated())->toBeFalse();
});

it('disables deactivate and delete for a workspace with platform staff in it', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(ViewWorkspace::class, ['record' => $this->admin->workspace()->getRouteKey()])
        ->assertActionVisible('deactivate')
        ->assertActionDisabled('deactivate')
        ->assertSee('Platform staff belong to this workspace. Switching it off would lock them out of this panel.')
        ->assertActionDisabled('delete')
        ->assertActionEnabled('messageLimit')
        ->assertActionEnabled('edit')
        ->callAction('deactivate', data: ['reason' => 'Testing']);

    expect($this->admin->fresh()->isDeactivated())->toBeFalse();
});

it('edits the workspace name and the owner\'s name and email', function (): void {
    viewWorkspace()
        ->assertActionVisible('edit')
        ->mountAction('edit')
        ->assertActionDataSet([
            'name' => 'Acme Corp',
            'owner_name' => 'Dana Owner',
            'owner_email' => 'dana@acme.test',
        ])
        ->fillForm(['name' => 'Acme GmbH', 'owner_name' => 'Dana Smith', 'owner_email' => 'dana@acme.example'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Workspace saved');

    $owner = $this->owner->fresh();
    expect($this->workspace->fresh()->name)->toBe('Acme GmbH')
        ->and($owner->name)->toBe('Dana Smith')
        ->and($owner->email)->toBe('dana@acme.example')
        ->and($owner->email_verified_at)->toBeNull();
});

it('keeps the owner\'s email confirmed when it does not change', function (): void {
    viewWorkspace()
        ->callAction('edit', data: ['name' => 'Acme GmbH', 'owner_name' => 'Dana Owner', 'owner_email' => 'dana@acme.test'])
        ->assertHasNoActionErrors();

    expect($this->owner->fresh()->email_verified_at)->not->toBeNull();
});

it('does not give the owner an email another account uses', function (): void {
    User::factory()->create(['email' => 'taken@acme.test']);

    viewWorkspace()
        ->callAction('edit', data: ['name' => 'Acme Corp', 'owner_name' => 'Dana Owner', 'owner_email' => 'taken@acme.test'])
        ->assertHasActionErrors(['owner_email' => 'unique']);

    viewWorkspace()
        ->callAction('edit', data: ['name' => '', 'owner_name' => 'Dana Owner', 'owner_email' => 'Dana@Acme.test'])
        ->assertHasActionErrors(['name' => 'required', 'owner_email' => 'lowercase']);

    expect($this->owner->fresh()->email)->toBe('dana@acme.test');
});

it('emails the owner a password reset link', function (): void {
    Notification::fake();

    viewWorkspace()
        ->callAction('sendPasswordResetLink')
        ->assertHasNoActionErrors()
        ->assertNotified('Password reset link sent');

    Notification::assertSentTo($this->owner, ResetPassword::class);

    viewWorkspace()
        ->callAction('sendPasswordResetLink')
        ->assertNotified('No link sent');

    Notification::assertSentToTimes($this->owner, ResetPassword::class, 1);
});

it('sets a new password for the owner', function (): void {
    viewWorkspace()
        ->callAction('setPassword', data: ['password' => ''])
        ->assertHasActionErrors(['password' => 'required']);

    viewWorkspace()
        ->callAction('setPassword', data: ['password' => 'a-new-secret-1'])
        ->assertHasNoActionErrors()
        ->assertNotified('Password changed');

    expect(Hash::check('a-new-secret-1', $this->owner->fresh()->password))->toBeTrue();
});

it('deletes the workspace only after its name is typed', function (): void {
    Queue::fake();

    viewWorkspace()
        ->callAction('delete', data: ['confirm_name' => 'Acme'])
        ->assertHasActionErrors(['confirm_name']);

    Queue::assertNothingPushed();

    viewWorkspace()
        ->callAction('delete', data: ['confirm_name' => 'Acme Corp'])
        ->assertHasNoActionErrors()
        ->assertRedirect(WorkspaceResource::getUrl('index'));

    Queue::assertPushed(DeleteWorkspaceJob::class, fn (DeleteWorkspaceJob $job): bool => $job->userId === $this->owner->id);
    expect($this->owner->fresh()->deactivation_reason)->toBe(DeleteAccountAction::DELETION_REASON);

    viewWorkspace()
        ->assertSee('Being deleted')
        ->assertActionHidden('delete')
        ->assertActionHidden('reactivate')
        ->assertActionHidden('deactivate')
        ->assertActionHidden('edit')
        ->assertActionHidden('sendPasswordResetLink')
        ->assertActionHidden('setPassword');
});

it('turns off the owner\'s two-step sign-in', function (): void {
    viewWorkspace()->assertActionHidden('disableTwoFactor');

    $this->owner->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
        'app_authentication_secret' => 'app-secret',
    ])->save();

    viewWorkspace()
        ->callAction('disableTwoFactor')
        ->assertHasNoActionErrors();

    $owner = $this->owner->fresh();
    expect($owner->two_factor_secret)->toBeNull()
        ->and($owner->two_factor_confirmed_at)->toBeNull()
        ->and($owner->app_authentication_secret)->toBeNull();
});

it('starts a data export for the owner once at a time', function (): void {
    Queue::fake();

    viewWorkspace()
        ->callAction('exportData')
        ->assertHasNoActionErrors()
        ->assertNotified('Export started');

    expect(DataExport::query()->where('user_id', $this->owner->id)->count())->toBe(1);
    Queue::assertPushed(PrepareDataExportJob::class);

    viewWorkspace()
        ->callAction('exportData')
        ->assertNotified('An export is already being prepared or can be downloaded');

    expect(DataExport::query()->where('user_id', $this->owner->id)->count())->toBe(1);
});

it('links to the dashboard filtered to the workspace', function (): void {
    viewWorkspace()
        ->assertActionHasUrl('openDashboard', Dashboard::getUrl(['filters' => ['workspace' => $this->workspace->id]]));
});
