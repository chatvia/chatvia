<?php

namespace Domain\Membership\Team\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $role_id
 * @property string $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Role $role
 * @property-read Team $team
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Guarded([
    'id',
])]
#[Table(name: 'team_invitations')]
class Invitation extends Model
{
    /**
     * Resolve an implicit route binding.
     *
     * The id is a bigint, but the route segment is attacker-controlled (the
     * invitation links are public, only signed). A non-numeric value such as
     * `.env` from a vulnerability scanner would otherwise reach Postgres and
     * throw a 22P02 "invalid text representation" QueryException. Returning
     * null for any non-integer id makes the binding miss cleanly so the
     * route's ->missing() callback redirects instead.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if (! ctype_digit((string) $value)) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    /**
     * Get the team this invitation is for.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the role this invitation is for.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
