<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Actions\Reindex\ReembedChunksAction;
use Domain\Knowledge\Embedding\Actions\Reindex\ReembedQaQuestionsAction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeEmbeddingsProvider;

/**
 * A re-index embeds the stored rows of an id range again with the model in
 * use: chunks from the text ingestion embedded (and their questions), Q&A
 * questions from their text. Rows outside the range keep their vectors.
 */

/**
 * A model that gives every text the same vector (0.5 everywhere: exact in
 * half precision) and remembers what it embedded.
 */
function reembedProvider(): FakeEmbeddingsProvider
{
    $provider = FakeEmbeddingsProvider::constant(0.5, 'new-model');

    app()->instance(EmbeddingsProviderInterface::class, $provider);

    return $provider;
}

/** A vector of the old model: 0.25 everywhere. */
function reembedOldVector(): string
{
    return '['.implode(',', array_fill(0, 1024, 0.25)).']';
}

function reembedChunk(SourceFile $file, string $text, ?string $questions = null, ?string $summary = null): Chunk
{
    return Chunk::factory()->forSource($file)->create([
        'chunk' => $text,
        'summary' => $summary,
        'questions' => $questions,
        'questions_embedding' => $questions === null ? null : reembedOldVector(),
        'embedding' => reembedOldVector(),
    ]);
}

/** Whether the row's vector column now holds the new model's vector. */
function reembedHasNewVector(string $table, string $column, int $id): bool
{
    $type = app(EmbeddingStorage::class)->columnType();

    return (bool) DB::scalar("SELECT {$column} = ?::{$type} FROM {$table} WHERE id = ?", ['['.implode(',', array_fill(0, 1024, 0.5)).']', $id]);
}

it('embeds the chunks of a range again from their text and questions', function (): void {
    $file = AgentFileFactory::new()->create();
    $asked = reembedChunk($file, 'Opening hours.', questions: 'When are you open?', summary: 'About hours.');
    $plain = reembedChunk($file, 'Prices.');
    $outside = reembedChunk($file, 'Outside the range.');
    $keywords = Chunk::query()->whereKey($asked->id)->value('embedding_bm25');
    $provider = reembedProvider();

    $done = app(ReembedChunksAction::class)->execute($asked->id, $plain->id);

    expect($done)->toBe(2)
        ->and($provider->texts)->toBe(["About hours.\n\nOpening hours.", 'Prices.', 'When are you open?'])
        ->and(reembedHasNewVector('chunks', 'embedding', $asked->id))->toBeTrue()
        ->and(reembedHasNewVector('chunks', 'questions_embedding', $asked->id))->toBeTrue()
        ->and(reembedHasNewVector('chunks', 'embedding', $plain->id))->toBeTrue()
        ->and(Chunk::query()->whereKey($plain->id)->value('questions_embedding'))->toBeNull()
        ->and(reembedHasNewVector('chunks', 'embedding', $outside->id))->toBeFalse()
        ->and(Chunk::query()->whereKey($asked->id)->value('embedding_bm25'))->toBe($keywords);
});

it('does nothing for a range without chunks', function (): void {
    $provider = reembedProvider();

    expect(app(ReembedChunksAction::class)->execute(PHP_INT_MAX - 1, PHP_INT_MAX))->toBe(0)
        ->and($provider->texts)->toBe([]);
});

it('embeds the Q&A questions of a range again', function (): void {
    Bus::fake();
    $agent = Agent::factory()->create();
    app(CreateAgentQaAction::class)->execute($agent, new QaEntryData('Hours', ['What are your hours?', 'When do you open?'], 'Nine to five.'));
    $questions = AgentQuestionAnswerQuestion::query()->where('agent_id', $agent->id)->orderBy('id')->get();
    AgentQuestionAnswerQuestion::query()->whereKey($questions->modelKeys())->update(['embedding' => reembedOldVector()]);
    $provider = reembedProvider();

    $done = app(ReembedQaQuestionsAction::class)->execute($questions->first()->id, $questions->last()->id);

    expect($done)->toBe(2)
        ->and($provider->texts)->toBe(['What are your hours?', 'When do you open?'])
        ->and($questions->every(fn (AgentQuestionAnswerQuestion $question): bool => reembedHasNewVector('agent_question_answer_questions', 'embedding', $question->id)))->toBeTrue();
});
