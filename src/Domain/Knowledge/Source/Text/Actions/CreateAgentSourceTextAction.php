<?php

namespace Domain\Knowledge\Source\Text\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;
use Throwable;

class CreateAgentSourceTextAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, User $user, string $rawContent): SourceText
    {
        $content = Str::of(TextCleaner::clean($rawContent))->trim()->toString();
        $numberOfPages = (int) ceil(str_word_count($content) / 450);

        if (! $user->consumption()->canUploadPages($numberOfPages)) {
            throw new DomainValidationException('content', 'This document requires '.$numberOfPages.' pages.');
        }

        $filepath = 'users/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/train/text/'.Str::uuid()->toString().'.txt';
        Storage::put($filepath, $content);

        try {
            /** @var SourceText $agentText */
            $agentText = DB::transaction(function () use ($agent, $filepath, $content, $numberOfPages): SourceText {
                $agentText = SourceText::create([
                    // Knowledge belongs to the workspace, not whoever uploaded it — key
                    // to the agent's owner so an employee's deletion can't cascade it away.
                    'user_id' => $agent->user_id,
                    'filepath' => $filepath,
                    'excerpt' => Str::of($content)->limit(200)->replace("\n", ' ')->trim()->toString(),
                    'length' => $numberOfPages,
                    'checksum' => md5($content),
                ]);

                $agent->texts()->attach($agentText->id);

                return $agentText;
            });
        } catch (Throwable $e) {
            // No row points at the file, so nothing else would ever delete it.
            Storage::delete($filepath);

            throw $e;
        }

        TextIngestionJob::dispatch($agentText, $agent);

        return $agentText;
    }
}
