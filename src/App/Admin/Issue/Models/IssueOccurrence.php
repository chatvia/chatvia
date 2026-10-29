<?php

declare(strict_types=1);

namespace App\Admin\Issue\Models;

use App\Admin\Issue\Enums\IssueActorKind;
use App\Admin\Issue\Enums\IssueOriginKind;
use App\Identity\ApiKey\Models\PersonalAccessToken;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Database\Factories\IssueOccurrenceFactory;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One time an {@see Issue} happened: when, who was behind it, the
 * workspace and agent it was for, what the app was doing (a request, a job,
 * a command), the model, and what the service answered. People are kept
 * by id only, so deleting one clears them here; deleting the workspace
 * deletes its occurrences.
 *
 * @property int $id
 * @property int $issue_id
 * @property int|null $workspace_id
 * @property int|null $agent_id
 * @property IssueActorKind $actor_kind
 * @property int|null $user_id
 * @property int|null $chatter_id
 * @property int|null $api_key_id
 * @property string|null $model
 * @property int|null $http_status
 * @property string|null $endpoint
 * @property string|null $message
 * @property IssueOriginKind $origin_kind
 * @property string|null $origin_name
 * @property Carbon $occurred_at
 */
#[Guarded(['id'])]
#[WithoutTimestamps]
class IssueOccurrence extends Model
{
    /** @use HasFactory<IssueOccurrenceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Issue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Chatter, $this>
     */
    public function chatter(): BelongsTo
    {
        return $this->belongsTo(Chatter::class);
    }

    /**
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'api_key_id');
    }

    /** Who was behind it, as Radar names them: "Jane Doe (staff)". */
    public function actorLabel(): string
    {
        $who = match ($this->actor_kind) {
            IssueActorKind::Staff => $this->user?->name,
            IssueActorKind::Chatter => $this->chatter?->email ?? $this->chatter?->name,
            IssueActorKind::ApiKey => $this->apiKey?->name,
            IssueActorKind::Visitor, IssueActorKind::System => null,
        };

        if ($who !== null) {
            return "{$who} ({$this->actor_kind->label()})";
        }

        return match ($this->actor_kind) {
            IssueActorKind::Visitor, IssueActorKind::System => $this->actor_kind->label(),
            default => "Deleted {$this->actor_kind->label()}",
        };
    }

    /** What the app was doing: "POST /chat/support", "Generate media (job)". */
    public function originLabel(): string
    {
        return match ($this->origin_kind) {
            IssueOriginKind::Request => $this->origin_name ?? 'A request',
            IssueOriginKind::Job => Str::of(class_basename($this->origin_name ?? ''))->beforeLast('Job')->headline()->lower()->ucfirst()->append(' (job)')->toString(),
            IssueOriginKind::Command => Str::of($this->origin_name ?? '')->prepend('php artisan ')->toString(),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_kind' => IssueActorKind::class,
            'origin_kind' => IssueOriginKind::class,
            'http_status' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
