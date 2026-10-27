<?php

declare(strict_types=1);

namespace Domain\Conversation\Services;

use Domain\Agent\Models\Agent;
use Illuminate\Support\Str;
use LogicException;
use Support\MimeType;

/**
 * Builds the storage folders of an agent's chats:
 * `workspaces/{workspace}/agents/{agent}/chat/{conversation}`. Inside, files
 * sit by kind: `images`, `videos`, `audio` and `files` for uploads and
 * generated media, `speech` for replies read aloud. A file uploaded before its
 * conversation exists waits in `chat/pending` and moves into the
 * conversation's folder once the conversation claims it.
 *
 * So deleting a conversation is one folder wipe, purging the agent wipes all
 * its chats, and deleting the workspace wipes everything under its prefix.
 * External ids are used so no internal id shows up in a storage path.
 */
final class ChatStoragePath
{
    /** MIME category ({@see MimeType::category()}) or generated-media kind => folder. */
    private const array KIND_FOLDERS = [
        'image' => 'images',
        'video' => 'videos',
        'audio' => 'audio',
    ];

    /** The folder for anything that is not an image, a video or audio. */
    private const string OTHER_FOLDER = 'files';

    /** Every chat of the agent. */
    public static function root(Agent $agent): string
    {
        return "{$agent->settingsStorageRoot()}/chat";
    }

    /**
     * One conversation's folder, or a subfolder of it.
     *
     * @throws LogicException when the conversation id is empty
     */
    public static function forConversation(Agent $agent, string $conversationId, string $subfolder = ''): string
    {
        // An empty segment would point at every chat of the agent, and
        // deleting a conversation wipes this path.
        if ($conversationId === '') {
            throw new LogicException('A conversation needs an external id before it has a storage folder.');
        }

        $path = self::root($agent)."/{$conversationId}";

        return $subfolder === '' ? $path : "{$path}/".Str::trim($subfolder, '/');
    }

    /** Uploads made before their conversation exists. */
    public static function pending(Agent $agent): string
    {
        return self::root($agent).'/pending';
    }

    /**
     * The subfolder a file goes in, from its MIME category or generated-media
     * kind (`image`, `video`, `audio`); anything else goes in `files`.
     */
    public static function kindFolder(?string $kind): string
    {
        return self::KIND_FOLDERS[$kind] ?? self::OTHER_FOLDER;
    }
}
