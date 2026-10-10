<?php

namespace Domain\Knowledge\Source\Text\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adds a text as a knowledge source of an agent and queues its ingestion.
 *
 * The cleaned text is written to storage first; the row and the agent link are
 * created in one transaction, and the stored file is deleted again when that
 * fails. The text is keyed to the agent's owner (the workspace), and its size
 * is stored in pages (~450 words each).
 */
class CreateAgentSourceTextAction
{
    /** Words per page. */
    private const int WORDS_PER_PAGE = 450;

    public function execute(Agent $agent, string $rawContent): SourceText
    {
        $content = Str::of(TextCleaner::clean($rawContent))->trim()->toString();
        $numberOfPages = (int) ceil(str_word_count($content) / self::WORDS_PER_PAGE);

        $filepath = KnowledgeStoragePath::forAgent($agent, 'train/text').'/'.Str::uuid()->toString().'.txt';
        Storage::put($filepath, $content);

        try {
            $sourceText = DB::transaction(function () use ($agent, $filepath, $content, $numberOfPages): SourceText {
                $sourceText = SourceText::query()->create([
                    // Knowledge belongs to the workspace, not whoever uploaded it — key
                    // to the agent's owner so an employee's deletion can't cascade it away.
                    'user_id' => $agent->user_id,
                    'filepath' => $filepath,
                    'excerpt' => Str::of($content)->limit(200)->replace("\n", ' ')->trim()->toString(),
                    'length' => $numberOfPages,
                    'checksum' => md5($content),
                ]);

                $agent->texts()->attach($sourceText->id);

                return $sourceText;
            });
        } catch (Throwable $e) {
            // No row points at the file, so nothing else would ever delete it.
            Storage::delete($filepath);

            throw $e;
        }

        TextIngestionJob::dispatch($sourceText, $agent);

        return $sourceText;
    }
}
