<?php

namespace App\Identity\Workspace\Concerns;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The teams a {@see User} owns or is a member of, and which one is current.
 * {@see currentOrPrimaryTeam()} is the read path (no DB writes, safe at render
 * time); only {@see switchTeam()} and {@see bootstrapCurrentTeam()} persist
 * `current_team_id`. Team roles and permissions are in
 * {@see ResolvesTeamMembership}.
 */
trait HasTeams
{
    /**
     * Get all teams where the user is a member (not owner).
     *
     * @return BelongsToMany<Team, $this>
     */
    public function memberTeams(): BelongsToMany
    {
        return $this
            ->belongsToMany(Team::class, 'team_user')
            ->withPivot('role_id');
    }

    /**
     * Get all teams owned by the user.
     *
     * @return HasMany<Team, $this>
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'user_id');
    }

    /**
     * Check if user owns a specific team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $team->user_id === $this->id;
    }

    /**
     * Check if user is a member or owner of a team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->ownsTeam($team) || $this->memberTeams->contains($team);
    }

    /**
     * Get the user's current active team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Switch to a different team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);

        return true;
    }

    /**
     * Resolve the user's current team WITHOUT persisting one (no DB writes,
     * safe to call from Inertia share lambdas and other render-time
     * contexts). Falls back to the user's primary owned team, then to the
     * first team they're a member of.
     *
     * For the writing variant — pick a current team AND persist the choice —
     * call {@see bootstrapCurrentTeam()} from a session-bootstrap middleware
     * or registration Action.
     */
    public function currentOrPrimaryTeam(): ?Team
    {
        return $this->remember('currentTeam', function (): ?Team {
            if ($this->current_team_id && $this->currentTeam) {
                return $this->currentTeam;
            }

            return $this->ownedTeams()->first() ?? $this->memberTeams()->first();
        });
    }

    /**
     * Like {@see currentOrPrimaryTeam()} but persists the choice when the user
     * had no `current_team_id` set. Call this on session bootstrap or
     * registration — NOT from render-time code (Inertia share lambda,
     * Blade composers, etc.) where a silent UPDATE is a foot-gun.
     */
    public function bootstrapCurrentTeam(): ?Team
    {
        if ($this->current_team_id && $this->currentTeam) {
            return $this->currentTeam;
        }

        $team = $this->ownedTeams()->first() ?? $this->memberTeams()->first();
        if ($team) {
            $this->update(['current_team_id' => $team->id]);
            // Invalidate the read cache so subsequent currentOrPrimaryTeam()
            // calls see the just-stamped row.
            unset($this->memo['currentTeam']);
        }

        return $team;
    }
}
