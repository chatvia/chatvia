<?php

namespace Domain\Conversation\Topic\Models;

use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single (conversation ↔ topic) link. Exactly one of `topic_id`
 * (committed) or `suggested_label` (emergent, pre-promotion) is set.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $chat_conversation_id
 * @property int|null $topic_id
 * @property string|null $suggested_label
 * @property-read Conversation $conversation
 * @property-read Topic|null $topic
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'conversation_topics')]
#[WithoutTimestamps]
class ConversationTopic extends Model
{
    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'chat_conversation_id');
    }

    /** @return BelongsTo<Topic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
