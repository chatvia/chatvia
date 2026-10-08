<?php

namespace Domain\Knowledge\Source\Text\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;
use Throwable;

class UpdateAgentSourceTextAction
{
    /**
     * Returns false when no-op (duplicate checksum already attached).
     *
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, User $user, SourceText $sourceText, string $rawContent): bool
    {
        $newContent = Str::of(TextCleaner::clean($rawContent))->trim()->toString();
        $checksum = md5($newContent);
        $numberOfPages = (int) ceil(str_word_count($newContent) / 450);

        if ($agent->texts()->where('checksum', $checksum)->exists()) {
            return false;
        }

        if (! $user->consumption()->canUploadPages($numberOfPages)) {
            throw new DomainValidationException('content', 'This document requires '.$numberOfPages.' pages.');
        }

        $oldFilepath = $sourceText->filepath;

        // Write the new file first and remove the old one only after the row
        // points at the new file, so a failure never leaves the text without
        // its content.
        $filepath = 'users/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/train/text/'.Str::uuid()->toString().'.txt';
        Storage::put($filepath, $newContent);

        try {
            DB::transaction(function () use ($sourceText, $filepath, $checksum, $numberOfPages, $newContent): void {
                $sourceText->update([
                    'filepath' => $filepath,
                    'checksum' => $checksum,
                    'length' => $numberOfPages,
                    'status' => SourceStatus::Pending->value,
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
