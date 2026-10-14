<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Actions\Reindex\ReembedChunksAction;
use Domain\Knowledge\Embedding\Actions\Reindex\ReembedQaQuestionsAction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * A re-index embeds the stored rows of an id range again with the model in
 * use: chunks from the text ingestion embedded (and their questions), Q&A
 * questions from their text. Rows outside the range keep their vectors.
 */

/**
 * A model that gives every text the same vector (0.5 everywhere: exact in
 * half precision) and remembers what it embedded.
 */
function reembedProvider(): object
{
    $provider = new class implements EmbeddingsProviderInterface
    {
        /** @var list<string> */
        public array $texts = [];

        public function embed(string $text, bool $returnAsText = false): string|array
        {
            $this->texts[] = $text;
            $vector = array_fill(0, 1024, 0.5);

            return $returnAsText ? '['.implode(',', $vector).']' : $vector;
        }

        public function embedQuery(string $text, bool $returnAsText = false): string|array
        {
            return $this->embed($text, $returnAsText);
        }

        public function embedBatch(array $texts): array
        {
            return array_map(fn (string $text): array => (array) $this->embed($text), $texts);
        }

        public function getDimensions(): int
        {
            return 1024;
        }

        public function getModelName(): string
        {
            return 'new-model';
        }
    };

    app()->instance(EmbeddingsProviderInterface::class, $provider);

    return $provider;
}

/** A vector of the old model: 0.25 everywhere. */
function oldModelVector(): string
{
    return '['.implode(',', array_fill(0, 1024, 0.25)).']';
}

function reembedChunk(SourceFile $file, string $text, ?string $questions = null, ?string $summary = null): Chunk
{
    return Chunk::query()->create([
        'workspace_id' => $file->workspace_id,
        'chunkable_type' => $file->getMorphClass(),
        'chunkable_id' => $file->id,
        'chunk_index' => 1,
        'chunk' => $text,
        'summary' => $summary,
        'questions' => $questions,
        'questions_embedding' => $questions === null ? null : oldModelVector(),
        'tokens' => 3,
        'embedding' => oldModelVector(),
        'embedding_bm25' => Bm25Tokenizer::expression($text),
        'checksum' => md5($text.uniqid()),
        'cost_in_micro_cents' => 0,
    ]);
}

/** Whether the row's vector column now holds the new model's vector. */
function hasNewVector(string $table, string $column, int $id): bool
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
        ->and(hasNewVector('chunks', 'embedding', $asked->id))->toBeTrue()
        ->and(hasNewVector('chunks', 'questions_embedding', $asked->id))->toBeTrue()
        ->and(hasNewVector('chunks', 'embedding', $plain->id))->toBeTrue()
        ->and(Chunk::query()->whereKey($plain->id)->value('questions_embedding'))->toBeNull()
        ->and(hasNewVector('chunks', 'embedding', $outside->id))->toBeFalse()
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
    AgentQuestionAnswerQuestion::query()->whereKey($questions->modelKeys())->update(['embedding' => oldModelVector()]);
    $provider = reembedProvider();

    $done = app(ReembedQaQuestionsAction::class)->execute($questions->first()->id, $questions->last()->id);

    expect($done)->toBe(2)
        ->and($provider->texts)->toBe(['What are your hours?', 'When do you open?'])
        ->and($questions->every(fn (AgentQuestionAnswerQuestion $question): bool => hasNewVector('agent_question_answer_questions', 'embedding', $question->id)))->toBeTrue();
});
