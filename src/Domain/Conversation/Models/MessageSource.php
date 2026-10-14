<?php

declare(strict_types=1);

namespace Domain\Conversation\Models;

use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of knowledge an assistant answer was given, at its rank (0 is
 * the best). The chunk may be gone since (a source was retrained or deleted);
 * the label, link and page stay, so the answer can still be explained.
 *
 * @property int $workspace_id
 * @property int $chat_message_id
 * @property int $rank
 * @property int|null $chunk_id
 * @property string $sourceable_type
 * @property int $sourceable_id
 * @property float|null $score
 * @property string $label
 * @property string|null $url
 * @property int|null $page
 * @property-read Message $message
 * @property-read Chunk|null $chunk
 *
 * @mixin \Eloquent
 */
#[Guarded([])]
#[Table(name: 'chat_message_sources', key: null)]
#[WithoutIncrementing]
#[WithoutTimestamps]
class MessageSource extends Model
{
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'score' => 'float',
            'page' => 'integer',
        ];
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'chat_message_id');
    }

    /** @return BelongsTo<Chunk, $this> */
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(Chunk::class);
    }
}
