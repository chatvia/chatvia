<?php

namespace App\Helpdesk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Auth\Models\User;

class Ticket extends Model
{
    protected $guarded = ['id'];

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
