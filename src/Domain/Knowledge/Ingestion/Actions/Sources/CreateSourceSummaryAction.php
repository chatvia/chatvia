<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Knowledge\Document\Conversion\OfficePdf;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Ingestion\Data\Analysis\ContentAnalysisData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\ChunkEmbeddingText;
use Domain\Knowledge\Ingestion\Services\ContentAnalyzer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Llm\Data\Chat\ChatResponseData;
use Domain\Llm\Models\Llm;
use Domain\Llm\Operations\Documents\Summarizer;
use Domain\Llm\Services\SourceSummaryModel;
use Domain\Llm\Services\TikToken;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Adds the summary chunk (chunk_index 0) of a source: one LLM call classifies
 * the whole document and one summarises it, both with the model for source
 * summaries. Skipped when summaries are off or no model reads PDF files: the
 * source is still searchable by its pages.
 */
class CreateSourceSummaryAction
{
    public function __construct(
        private readonly ContentAnalyzer $contentAnalyzer,
        private readonly Summarizer $summarizer,
        private readonly SourceSummaryModel $summaryModel,
        private readonly EmbeddingsProvider $embeddings,
        private readonly OfficePdf $officePdf,
    ) {}

    public function execute(SourceFile|SourceText|SourceWebsite $source): ?Chunk
    {
        $model = $this->summaryModel->find();

        if (! $model instanceof Llm || $source->summary()->exists()) {
            return null;
        }

        [$analysis, $summary] = $this->readDocument($source);

        $summaryText = $summary->content;
        $plainSummary = ChunkEmbeddingText::forSummary($summaryText);
        $questions = Arr::join($analysis->questions, "\n");

        return Chunk::query()->create([
            'workspace_id' => $source->workspace_id,
            'chunkable_type' => $source->getMorphClass(),
            'chunkable_id' => $source->id,
            'chunk_index' => 0,
            'chunk' => $summaryText,
            'summary' => '',
            'questions' => $questions !== '' ? $questions : null,
            'questions_embedding' => $questions !== '' ? $this->embeddings->embed($questions, true) : null,
            'chunked_using_model' => $model->name,
            'tokens' => TikToken::countTokens($summaryText),
            'embedding' => $this->embeddings->embed($plainSummary, true),
            'search_text' => $plainSummary,
            'metadata' => $analysis->metadata->toArray(),
            'checksum' => hash('xxh128', $summaryText),
        ]);
    }

    /**
     * Classify and summarise the document. The PDF goes inside the request as
     * a data: URL rather than a link to this site: a model handed a link it
     * cannot open (a local or private install) summarises the link instead of
     * the document. withPdf() deletes a temporary converted PDF once both
     * calls are done, and never touches a stored original PDF. The detected
     * language and type are saved on the source.
     *
     * @return array{0: ContentAnalysisData, 1: ChatResponseData}
     */
    private function readDocument(SourceFile|SourceText|SourceWebsite $source): array
    {
        return $source->withPdf($this->officePdf, function (string $pdfPath) use ($source): array {
            $pdfUrl = 'data:application/pdf;base64,'.base64_encode(
                Storage::get($pdfPath) ?? throw new RuntimeException("File not found in storage: {$pdfPath}"),
            );

            $analysis = $this->contentAnalyzer->analyze($pdfUrl);

            $source->update([
                'language' => $analysis->language,
                'document_classification' => $analysis->document_type,
            ]);

            return [$analysis, $this->summarizer->summarize($pdfUrl, $analysis->language)];
        });
    }
}
