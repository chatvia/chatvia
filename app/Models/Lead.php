<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'agent_id',
        'name',
        'email',
        'phone',
        'company',
        'message',
        'custom_fields',
        'status',
        'ip_address',
        'user_agent',
        'referrer',
        'contacted_at',
        'qualified_at',
        'converted_at',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'contacted_at' => 'datetime',
        'qualified_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'new' => 'blue',
            'contacted' => 'yellow',
            'qualified' => 'green',
            'converted' => 'purple',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'new' => 'New',
            'contacted' => 'Contacted',
            'qualified' => 'Qualified',
            'converted' => 'Converted',
            default => 'Unknown',
        };
    }
}
