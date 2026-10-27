<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Media;

use Domain\Conversation\Data\Media\MediaModelPickData;
use Domain\Conversation\Models\Conversation;

/**
 * Keeps the image and video model the chatter picked with this turn on the
 * conversation, where the image and video tools find it: they run as their
 * own requests and know the conversation, not the message. Written only when
 * the pick changed.
 */
class RememberMediaModelPickAction
{
    public function execute(Conversation $conversation, MediaModelPickData $pick): void
    {
        $stored = $pick->toStored();

        if ($stored === ($conversation->media_models ?? [])) {
            return;
        }

        $conversation->forceFill(['media_models' => $stored === [] ? null : $stored])->save();
    }
}
