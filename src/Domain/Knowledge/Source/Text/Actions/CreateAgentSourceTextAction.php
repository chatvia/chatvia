<?php

namespace Domain\Knowledge\Source\Text\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;
use Throwable;

/**
 * Adds a text as a knowledge source of an agent and queues its ingestion.
 *
 * The cleaned text is written to storage first, into the source's own folder
 * ({@see KnowledgeStoragePath}), so the source's external id is chosen before
 * the row exists. The row and the agent link are created in one transaction,
 * and the stored file is deleted again when that fails. The text is keyed to the agent's owner (the workspace), and its size
 * is stored in pages (~450 words each). A workspace holds each text once: the
 * same content again is a validation error on `content`.
 */
class CreateAgentSourceTextAction
{
    /** Words per page. */
    private const int WORDS_PER_PAGE = 450;

    /**
     * @throws DomainValidationException when the workspace already has this text
     */
    public function execute(Agent $agent, string $rawContent): SourceText
    {
        $content = Str::of(TextCleaner::clean($rawContent))->trim()->toString();
        $checksum = md5($content);
        $numberOfPages = (int) ceil(Str::wordCount($content) / self::WORDS_PER_PAGE);

        if (SourceText::query()->where('workspace_id', $agent->workspace_id)->where('checksum', $checksum)->exists()) {
            throw $this->duplicate();
        }

        // Knowledge belongs to the workspace, not whoever uploaded it — key
        // to the agent's owner so an employee's deletion can't cascade it away.
        $sourceText = new SourceText([
            'workspace_id' => $agent->workspace_id,
            'external_id' => (string) Str::uuid7(),
            'status' => SourceStatus::Pending,
        ]);

        $filepath = KnowledgeStoragePath::forSource($sourceText).'/'.Str::uuid7()->toString().'.txt';
        Storage::put($filepath, $content);

        try {
            DB::transaction(function () use ($sourceText, $agent, $filepath, $content, $checksum, $numberOfPages): void {
                $sourceText->fill([
                    'filepath' => $filepath,
                    'excerpt' => Str::of($content)->limit(200)->replace("\n", ' ')->trim()->toString(),
                    'length' => $numberOfPages,
                    'checksum' => $checksum,
                ])->save();

                $agent->texts()->attach($sourceText->id);
            });
        } catch (Throwable $e) {
            // No row points at the file, so nothing else would ever delete it.
            Storage::delete($filepath);

            // The same text saved at the same moment by another request.
            throw $e instanceof UniqueConstraintViolationException ? $this->duplicate() : $e;
        }

        TextIngestionJob::dispatch($sourceText, $agent);

        return $sourceText;
    }

    private function duplicate(): DomainValidationException
    {
        return new DomainValidationException('content', __('This text is already in your knowledge base.'));
    }
}
