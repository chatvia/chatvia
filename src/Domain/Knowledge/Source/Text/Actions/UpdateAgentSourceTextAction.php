<?php

namespace Domain\Knowledge\Source\Text\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;
use Throwable;

/**
 * Replaces the content of a text source and queues it for ingestion again.
 * Nothing happens when the agent already has a text with the same content.
 *
 * The new content is written to storage before the row points at it, and the
 * old file is removed only afterwards, so a failure never leaves the text
 * without content. The old chunks are deleted in the same transaction.
 */
class UpdateAgentSourceTextAction
{
    /** Words per quota page. */
    private const int WORDS_PER_PAGE = 450;

    /**
     * Returns false when no-op (duplicate checksum already attached).
     *
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, User $user, SourceText $sourceText, string $rawContent): bool
    {
        $newContent = Str::of(TextCleaner::clean($rawContent))->trim()->toString();
        $checksum = md5($newContent);
        $numberOfPages = (int) ceil(str_word_count($newContent) / self::WORDS_PER_PAGE);

        if ($agent->texts()->where('checksum', $checksum)->exists()) {
            return false;
        }

        if (! $user->consumption()->canUploadPages($numberOfPages)) {
            throw new DomainValidationException('content', __('This document requires :pages pages.', ['pages' => $numberOfPages]));
        }

        $oldFilepath = $sourceText->filepath;

        // Write the new file first and remove the old one only after the row
        // points at the new file, so a failure never leaves the text without
        // its content.
        $filepath = KnowledgeStoragePath::forAgent($agent, 'train/text').'/'.Str::uuid()->toString().'.txt';
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

                $sourceText->chunks()->delete();
            });
        } catch (Throwable $e) {
            Storage::delete($filepath);

            throw $e;
        }

        if ($oldFilepath !== $filepath) {
            Storage::delete($oldFilepath);
        }

        TextIngestionJob::dispatch($sourceText, $agent);

        return true;
    }
}
