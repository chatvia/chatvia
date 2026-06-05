<?php

namespace Domain\Knowledge\Source\Text\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Membership\Team\Exceptions\InviteRejectedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateAgentSourceTextAction
{
    /**
     * Returns false when no-op (duplicate checksum already attached).
     *
     * @throws InviteRejectedException
     */
    public function execute(Agent $agent, User $user, SourceText $sourceText, string $rawContent): bool
    {
        $newContent = Str::of(TextCleaner::clean($rawContent))->trim()->toString();
        $checksum = md5($newContent);
        $numberOfPages = (int) ceil(str_word_count($newContent) / 450);

        if ($agent->texts()->where('checksum', $checksum)->exists()) {
            return false;
        }

        if (! $user->canUploadPages($numberOfPages)) {
            throw new InviteRejectedException('content', 'This document requires '.$numberOfPages.' pages.');
        }

        Storage::delete($sourceText->filepath);

        $filepath = 'users/'.$agent->user_id.'/agents/'.$agent->id.'/train/text/'.Str::uuid()->toString().'.txt';
        Storage::put($filepath, $newContent);

        $sourceText->update([
            'filepath' => $filepath,
            'checksum' => $checksum,
            'length' => $numberOfPages,
            'status' => 'pending',
            'excerpt' => Str::of($newContent)->limit(200)->replace("\n", ' ')->trim()->toString(),
        ]);

        $sourceText->chunks()->delete();
        $sourceText->startTraining($agent);

        return true;
    }
}
