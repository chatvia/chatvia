<?php

declare(strict_types=1);

namespace Domain\Conversation\Services;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Speech\MessageSpeechFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use stdClass;
use Throwable;

/**
 * Wipes the chat folder of deleted conversations ({@see ChatStoragePath}):
 * uploads, generated media and replies read aloud, in one folder delete each.
 * Every path that deletes conversations calls this, the bulk ones too, since
 * they skip model events. Rows that point at a file (attachments, generated
 * media) are still deleted through their models; this also removes the files
 * no row points at (replies read aloud) and anything a failed delete left.
 *
 * The wipe waits for the commit, like the per-file deletes, so a rolled-back
 * delete keeps its files.
 */
final class ConversationFiles
{
    /** The disks chat files are written to. */
    private const array DISKS = [Attachment::DISK, GeneratedMedia::DISK, MessageSpeechFiles::DISK];

    /**
     * Each agent is read once, a deleted one too.
     *
     * @param  iterable<Conversation|stdClass>  $conversations  each with its `agent_id` and `external_id`
     */
    public function forget(iterable $conversations): void
    {
        $conversations = collect($conversations);

        if ($conversations->isEmpty()) {
            return;
        }

        $agents = Agent::withTrashed()->whereIn('id', $conversations->pluck('agent_id')->unique())->get()->keyBy('id');

        $folders = $conversations
            ->map(static function (Conversation|stdClass $conversation) use ($agents): ?string {
                $agent = $agents->get((int) $conversation->agent_id);

                return $agent instanceof Agent ? ChatStoragePath::forConversation($agent, (string) $conversation->external_id) : null;
            })
            ->filter()
            ->values()
            ->all();

        DB::afterCommit(static function () use ($folders): void {
            foreach (array_unique(self::DISKS) as $disk) {
                foreach ($folders as $folder) {
                    try {
                        Storage::disk($disk)->deleteDirectory($folder);
                    } catch (Throwable $e) {
                        Log::warning('Conversation files: failed to wipe a chat folder.', [
                            'disk' => $disk,
                            'folder' => $folder,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        });
    }
}
