<?php

namespace Domain\Helpdesk\Models;

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $type
 * @property string $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReply newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReply newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReply query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReply whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReply whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReply whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReply whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReply whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReply whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReply whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class TicketReply extends Model
{
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
