<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Services;

use Domain\Agent\Models\Agent;

/**
 * Builds the storage folders that knowledge files are written to. Every path
 * starts with the owning workspace's prefix (`workspaces/{workspace}/agents/{agent}`),
 * so deleting a workspace or an agent removes all its files with one prefix
 * wipe. External ids are used so no internal id shows up in a storage path.
 */
final class KnowledgeStoragePath
{
    public static function forAgent(Agent $agent, string $subfolder = ''): string
    {
        $path = $agent->storageRoot().'/agents/'.$agent->external_id;

        return $subfolder === '' ? $path : $path.'/'.trim($subfolder, '/');
    }
}
