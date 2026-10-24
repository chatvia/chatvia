<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Retrieval\Services\KnowledgeScope;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;

/**
 * The short names of what an agent's knowledge covers, given to the model
 * when nothing matches: Q&A titles and file names without the extension,
 * each once, and nothing from another agent.
 */
it('names the agent\'s Q&A titles and files, each once', function (): void {
    $agent = createAgent(User::factory()->create());
    // Newest first; the older copy of the same name is left out.
    AgentQuestionAnswer::query()->create(['agent_id' => $agent->id, 'title' => 'shipping times', 'questions' => 'Shipping?', 'answer' => 'Two days.']);
    AgentQuestionAnswer::query()->create(['agent_id' => $agent->id, 'title' => 'Shipping times', 'questions' => 'How long is shipping?', 'answer' => 'Two days.']);
    $agent->files()->attach(AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'filename' => 'Return_policy-2026.pdf'])->id);

    $other = createAgent(User::factory()->create());
    AgentQuestionAnswer::query()->create(['agent_id' => $other->id, 'title' => 'Secret pricing', 'questions' => 'Price?', 'answer' => 'No.']);

    expect(app(KnowledgeScope::class)->names($agent))->toBe(['Shipping times', 'Return policy 2026']);
});

it('has no names for an agent without knowledge', function (): void {
    expect(app(KnowledgeScope::class)->names(createAgent(User::factory()->create())))->toBe([]);
});
