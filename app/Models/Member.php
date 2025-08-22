<?php

namespace App\Models;

use App\Notifications\MemberInvitationNotification;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;

class Member extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function invite(Agent $agent): void
    {
        $this->notify(new MemberInvitationNotification($agent, $this));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class);
    }

    public function hasAccessToAgent(Agent|int $agent): bool
    {
        $agentId = $agent instanceof Agent ? $agent->id : $agent;

        return $this->agents()->where('agent_id', $agentId)->exists();
    }
}
