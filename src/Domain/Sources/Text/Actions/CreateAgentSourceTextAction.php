<?php

namespace Domain\Sources\Text\Actions;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Ingestion\Services\Tidy\TextCleaner;
use Domain\Sources\Text\Models\SourceText;
use Domain\Teams\Exceptions\InviteRejectedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateAgentSourceTextAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(Agent $agent, User $user, string $rawContent): SourceText
    {
        $content = Str::of(TextCleaner::clean($rawContent))->trim()->toString();
        $numberOfPages = (int) ceil(str_word_count($content) / 450);

        if ($user->canUploadPages($numberOfPages)) {
            throw new InviteRejectedException('content', 'This document requires '.$numberOfPages.' pages.');
        }

        $filepath = 'users/'.$agent->user_id.'/agents/'.$agent->id.'/train/text/'.Str::uuid()->toString().'.txt';
        Storage::put($filepath, $content);

        /** @var SourceText $agentText */
        $agentText = SourceText::create([
            'user_id' => $user->id,
            'filepath' => $filepath,
            'excerpt' => Str::of($content)->limit(200)->replace("\n", ' ')->trim()->toString(),
            'length' => $numberOfPages,
            'checksum' => md5($content),
        ]);

        $agent->texts()->attach($agentText->id);
        $agentText->startTraining($agent);

        return $agentText;
    }
}
