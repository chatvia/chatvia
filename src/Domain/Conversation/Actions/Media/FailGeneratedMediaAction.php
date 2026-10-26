<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Media;

use App\Identity\Workspace\Services\GenerationAllowance;
use Domain\Conversation\Data\Media\GeneratedMediaData;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Models\GeneratedMedia;

/**
 * Marks a generated item that could not be made, and why: the service
 * refused it, failed, or ran out of time. Its slot of the workspace's
 * monthly limit is given back, and the chat is told at once, so the
 * placeholder turns into a short message. An item already settled is left
 * as it is, so a late timeout never undoes a made file.
 */
class FailGeneratedMediaAction
{
    public function __construct(
        private readonly GenerationAllowance $allowance,
    ) {}

    public function execute(GeneratedMedia $media, GeneratedMediaFailure $failure): void
    {
        $failed = GeneratedMedia::query()
            ->whereKey($media->id)
            ->where('status', GeneratedMediaStatus::Pending)
            ->update(['status' => GeneratedMediaStatus::Failed, 'failure' => $failure, 'updated_at' => now()]);

        if ($failed === 0) {
            return;
        }

        $media->refresh();
        $this->allowance->release($media->workspace_id, $media->created_at ?? now());

        GeneratedMediaUpdated::dispatch($media->conversation->external_id, GeneratedMediaData::fromModel($media));
    }
}
