<?php

namespace Domain\Chat\Models;

use Support\Traits\SupportsExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Auth\Models\User;
use App\Country\Models\Country;
use Domain\Agents\Models\Agent;

class ChatSession extends Model
{
    use HasFactory, SupportsExternalId;

    protected $guarded = ['id'];

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
