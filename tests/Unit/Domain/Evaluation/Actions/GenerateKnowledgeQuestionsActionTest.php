<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Evaluation\Actions\GenerateKnowledgeQuestionsAction;
use Domain\Evaluation\Data\KnowledgeQuestionCandidateData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Enums\LlmTask;
use Illuminate\Support\Str;

/**
 * Reading the model's reply: a question is kept when it names a passage of
 * the sample and fits a case name; a blank one, one for a passage that was
 * not sent, one too long for a case name, and a repeat (case-insensitive)
 * are dropped; the count caps what is kept.
 */
it('keeps the questions that name a sent passage, dropping blanks, strays, overlong ones and repeats', function (): void {
    $agent = createAgent(User::factory()->create());
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $file->agents()->attach($agent->id);
    $chunk = Chunk::factory()->forSource($file)->create(['chunk' => 'We open at nine.']);
    config([LlmTask::Starters->configKey() => testLlm()->id]);
    fakeLlmCompletion(json_encode(['questions' => [
        ['passage' => 1, 'question' => '  When do you open?  '],
        ['passage' => 1, 'question' => ''],
        ['passage' => 7, 'question' => 'Where are you?'],
        ['passage' => 1, 'question' => Str::repeat('x', 256)],
        ['passage' => 1, 'question' => 'when do you open?'],
        ['passage' => 1, 'question' => 'Are you open at nine?'],
        ['passage' => 1, 'question' => 'One too many?'],
    ]], JSON_THROW_ON_ERROR));

    $questions = app(GenerateKnowledgeQuestionsAction::class)->execute($agent, 2);

    expect($questions->map(fn (KnowledgeQuestionCandidateData $q): string => $q->question)->all())
        ->toBe(['When do you open?', 'Are you open at nine?'])
        ->and($questions->map(fn (KnowledgeQuestionCandidateData $q): int => $q->id)->unique()->all())->toBe([$chunk->id])
        ->and($questions->first()->key)->toBe("ai:{$chunk->id}:0");
});
