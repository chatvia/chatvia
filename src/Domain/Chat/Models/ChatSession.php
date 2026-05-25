<?php

namespace Domain\Chat\Models;

use App\Auth\Models\User;
use App\Country\Models\Country;
use Domain\Agents\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Support\Traits\SupportsExternalId;

/**
 * @property int $id
 * @property string $external_id
 * @property int|null $agent_id
 * @property int|null $user_id
 * @property int|null $country_id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $ip_address
 * @property string|null $region
 * @property string|null $city
 * @property string|null $zip_code
 * @property string|null $time_zone
 * @property bool|null $is_proxy
 * @property string|null $user_agent
 * @property string|null $device_type
 * @property string|null $browser
 * @property string|null $os
 * @property string|null $referrer_url
 * @property string|null $page_url
 * @property string|null $locale
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent|null $agent
 * @property-read Collection<int, ChatConversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read Country|null $country
 * @property-read User|null $user
 *
 * @method static \Database\Factories\ChatSessionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereBrowser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereDeviceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereIsProxy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereLastActivityAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereOs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession wherePageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereReferrerUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereTimeZone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatSession whereZipCode($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class ChatSession extends Model
{
    use HasFactory, SupportsExternalId;

    protected array $hmacEncryptionColumns = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'chat_session_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
