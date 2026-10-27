<?php

declare(strict_types=1);

use Domain\Conversation\Actions\Media\RememberMediaModelPickAction;
use Domain\Conversation\Data\Media\MediaModelPickData;
use Domain\Conversation\Models\Conversation;

/**
 * The image and video model picked with a turn is kept on the conversation,
 * where the media tools read it; an empty pick clears it.
 */
it('keeps the pick on the conversation', function (): void {
    $conversation = Conversation::factory()->create();

    app(RememberMediaModelPickAction::class)->execute($conversation, new MediaModelPickData(image: 'fal-ai/qwen-image'));

    expect($conversation->fresh()->media_models)->toBe(['image' => 'fal-ai/qwen-image']);

    app(RememberMediaModelPickAction::class)->execute($conversation, new MediaModelPickData);

    expect($conversation->fresh()->media_models)->toBeNull();
});

it('writes nothing when the pick did not change', function (): void {
    $conversation = Conversation::factory()->create(['media_models' => ['image' => 'a', 'video' => 'b']]);
    $updatedAt = $conversation->updated_at;
    $this->travel(1)->minutes();

    app(RememberMediaModelPickAction::class)->execute($conversation, new MediaModelPickData(image: 'a', video: 'b'));

    expect($conversation->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});
