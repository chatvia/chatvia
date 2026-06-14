<?php

namespace Domain\Conversation\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;
use Support\Localization\Country\Models\Country;

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
 * @property-read Collection<int, Conversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read Country|null $country
 * @property-read User|null $user
 *
 * @method static \Database\Factories\SessionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereBrowser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereDeviceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereIsProxy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereLastActivityAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereOs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session wherePageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereReferrerUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereTimeZone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Session whereZipCode($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'chat_sessions')]
class Session extends Model
{
    use HasExternalId, HasFactory;

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'is_proxy' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'chat_session_id');
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
