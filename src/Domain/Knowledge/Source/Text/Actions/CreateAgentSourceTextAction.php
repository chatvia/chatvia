<?php

namespace Domain\Knowledge\Source\Text\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;

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

        $filepath = 'users/'.$agent->user_id.'/agents/'.$agent->id.'/train/text/'.Str::uuid()->toString().'.txt';
        Storage::put($filepath, $content);

        /** @var SourceText $agentText */
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
        TextIngestionJob::dispatch($agentText, $agent);

        return $agentText;
    }
}
