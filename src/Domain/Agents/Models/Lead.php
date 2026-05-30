<?php

namespace Domain\Agents\Models;

use App\Auth\Models\User;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $agent_id
 * @property int $chat_conversation_id
 * @property int|null $assigned_to
 * @property string $status
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $job_title
 * @property string|null $company
 * @property string|null $location
 * @property string|null $message
 * @property string|null $referrer_url
 * @property string|null $page_url
 * @property string|null $locale
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $contacted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent|null $agent
 * @property-read User|null $assignedTo
 * @property-read ChatConversation|null $conversation
 * @property-read Collection<int, ChatMessage> $messages
 * @property-read int|null $messages_count
 *
 * @method static \Database\Factories\LeadFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereChatConversationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereCompany($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereJobTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead wherePageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereReferrerUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lead whereUserAgent($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class Lead extends Model
{
    use HasFactory;

    protected $casts = [
        'contacted_at' => 'datetime',
    ];

    // Relationships
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_conversation_id', 'chat_conversation_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
