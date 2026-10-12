<?php

namespace Domain\Conversation\Models;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Database\Factories\SessionFactory;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Listeners\LocateSession;
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
 * A visitor's identity with one agent, kept across all of their conversations
 * (not the browser-bound WidgetSession). It holds what the chat log and
 * analytics show about the visitor: contact details when given, device and
 * browser, the page they came from, and a location that is filled in later
 * by {@see LocateSession}. A signed-in chatter's session links to the
 * chatter (`chatter_id`); a staff account's (a dashboard preview) to the user
 * (`user_id`); never both.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property int|null $agent_id
 * @property int|null $user_id
 * @property int|null $chatter_id
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
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent|null $agent
 * @property-read Collection<int, Conversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read Country|null $country
 * @property-read User|null $user
 * @property-read Chatter|null $chatter
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
    /** @use HasFactory<SessionFactory> */
    use HasExternalId, HasFactory;

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'anonymized_at' => 'datetime',
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

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * The staff account chatting (a dashboard preview), if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The chatter chatting, if any.
     *
     * @return BelongsTo<Chatter, $this>
     */
    public function chatter(): BelongsTo
    {
        return $this->belongsTo(Chatter::class);
    }

    /** The signed-in person on this session (chatter or staff), or null for a visitor. */
    public function viewer(): ?ChatViewerData
    {
        return match (true) {
            $this->chatter_id !== null => ChatViewerData::chatter($this->chatter_id),
            $this->user_id !== null => ChatViewerData::staff($this->user_id),
            default => null,
        };
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
