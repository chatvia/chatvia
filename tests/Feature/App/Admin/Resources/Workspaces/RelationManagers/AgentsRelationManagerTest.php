<?php

use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Admin\Resources\Workspaces\RelationManagers\AgentsRelationManager;
use App\Identity\Workspace\Models\User;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * The Agents tab of a workspace's page in the admin panel: the workspace's
 * agents only, each with its model, where it answers, its knowledge and this
 * month's traffic.
 */
it('lists the workspace\'s agents with model, channels, knowledge and traffic', function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    $owner = User::factory()->create();
    $agent = createAgent($owner, ['name' => 'Acme Helper', 'is_public' => true, 'llm_id' => testLlm('qwen3-test')->id]);
    $agent->setSetting(ConversationSource::Bar->value, false, 'surfaces');
    AgentTelegramBot::factory()->forAgent($agent)->create();
    SourceText::factory()->count(2)->attachedTo($agent)->create();
    analyticsRollup($agent, '2031-03-18 09:00:00', ['user_messages' => 10, 'assistant_messages' => 12, 'conversations_started' => 3]);

    $quiet = createAgent($owner, ['name' => 'Quiet Bot']);
    $elsewhere = createAgent(User::factory()->create(), ['name' => 'Other Bot']);

    Livewire::actingAs(adminUser(), 'admin')
        ->test(AgentsRelationManager::class, ['ownerRecord' => $owner->workspace(), 'pageClass' => ViewWorkspace::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$agent, $quiet])
        ->assertCanNotSeeTableRecords([$elsewhere])
        ->assertTableColumnStateSet('llm.name', 'qwen3-test', $agent)
        ->assertTableColumnStateSet('answers_on', ['Hosted Chat', 'Chat Widget', 'Telegram'], $agent)
        ->assertTableColumnStateSet('sources_count', 2, $agent)
        ->assertTableColumnStateSet('messages_this_month', 22, $agent)
        ->assertTableColumnStateSet('conversations_this_month', 3, $agent)
        ->assertTableColumnStateSet('messages_this_month', 0, $quiet)
        ->assertTableColumnStateSet('last_active_at', null, $quiet)
        ->assertSee('Public');
});
