<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Jurager\Teams\Models\Role as JuragerRole;

class Role extends JuragerRole
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'all_agents',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'all_agents' => 'boolean',
        ];
    }

    /**
     * Get the agents this role has access to.
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class);
    }
}
