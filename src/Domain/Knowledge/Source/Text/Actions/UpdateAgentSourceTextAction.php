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
 * Replaces the content of a text source and queues it for ingestion again.
 * Nothing happens when the content did not change; content another text of
 * the workspace already has is a validation error on `content`.
 *
 * The new content is written to the source's storage folder
 * ({@see KnowledgeStoragePath}) before the row points at it, and the old file
 * is removed only afterwards, so a failure never leaves the text without
 * content. The old chunks, the summary chunk included, are deleted in
 * the same transaction, so the summary is written again for the new content.
 */
class UpdateAgentSourceTextAction
{
    /** Words per page. */
    private const int WORDS_PER_PAGE = 450;

    /**
     * Returns false when the content did not change.
     *
     * @throws DomainValidationException when another text of the workspace has this content
     */
    public function execute(Agent $agent, SourceText $sourceText, string $rawContent): bool
    {
        $newContent = Str::of(TextCleaner::clean($rawContent))->trim()->toString();
        $checksum = md5($newContent);
        $numberOfPages = (int) ceil(Str::wordCount($newContent) / self::WORDS_PER_PAGE);

        if ($sourceText->checksum === $checksum) {
            return false;
        }

        $duplicate = SourceText::query()
            ->where('workspace_id', $sourceText->workspace_id)
            ->where('checksum', $checksum)
            ->whereKeyNot($sourceText->id)
            ->exists();

        if ($duplicate) {
            throw $this->duplicate();
        }

        $oldFilepath = $sourceText->filepath;

        // Write the new file first and remove the old one only after the row
        // points at the new file, so a failure never leaves the text without
        // its content.
        $filepath = KnowledgeStoragePath::forSource($sourceText).'/'.Str::uuid7()->toString().'.txt';
        Storage::put($filepath, $newContent);

        try {
            DB::transaction(function () use ($sourceText, $filepath, $checksum, $numberOfPages, $newContent): void {
                $sourceText->update([
                    'filepath' => $filepath,
                    'checksum' => $checksum,
                    'length' => $numberOfPages,
                    'status' => SourceStatus::Pending,
                    'excerpt' => Str::of($newContent)->limit(200)->replace("\n", ' ')->trim()->toString(),
                ]);

                $sourceText->deleteChunks();
            });
        } catch (Throwable $e) {
            Storage::delete($filepath);

            // The same text saved at the same moment by another request.
            throw $e instanceof UniqueConstraintViolationException ? $this->duplicate() : $e;
        }

        if ($oldFilepath !== $filepath) {
            Storage::delete($oldFilepath);
        }

        TextIngestionJob::dispatch($sourceText, $agent);

        return true;
    }

    private function duplicate(): DomainValidationException
    {
        return new DomainValidationException('content', __('This text is already in your knowledge base.'));
    }
}
