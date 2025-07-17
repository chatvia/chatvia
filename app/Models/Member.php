<?php

namespace App\Models;

use App\Notifications\MemberInvitationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Member extends Model
{
    use Notifiable;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function invite(Agent $agent): void
    {
        $this->notify(new MemberInvitationNotification($agent, $this));
    }
}
