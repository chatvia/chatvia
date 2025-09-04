<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'tags' => 'array',
        'custom_fields' => 'array',
        'contacted_at' => 'datetime',
        'qualified_at' => 'datetime',
        'converted_at' => 'datetime',
        'lost_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'next_follow_up' => 'datetime',
        'score' => 'decimal:2',
        'value' => 'decimal:2',
        'page_views' => 'integer',
        'session_duration' => 'integer',
    ];

    // Relationships
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage', 'slug');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderBy('created_at', 'desc');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(LeadComment::class)->orderBy('created_at', 'desc');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(LeadTask::class)->orderBy('due_date', 'asc');
    }

    public function pendingTasks(): HasMany
    {
        return $this->hasMany(LeadTask::class)->where('status', 'pending');
    }

    public function overdueTasks(): HasMany
    {
        return $this->hasMany(LeadTask::class)
            ->where('due_date', '<', now())
            ->where('status', '!=', 'completed');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id', 'conversation_id')
            ->where('agent_id', $this->agent_id);
    }

    // Scopes
    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where('assigned_to', $user->id);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByPipelineStage(Builder $query, string $stage): Builder
    {
        return $query->where('pipeline_stage', $stage);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeWithTags(Builder $query, array $tags): Builder
    {
        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhereJsonContains('tags', $tag);
            }
        });
    }

    public function scopeHighValue(Builder $query, float $minValue = 1000): Builder
    {
        return $query->where('value', '>=', $minValue);
    }

    public function scopeRecentActivity(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_activity_at', '>=', now()->subDays($days));
    }

    public function scopeNeedingFollowUp(Builder $query): Builder
    {
        return $query->where('next_follow_up', '<=', now())
            ->whereNotNull('next_follow_up');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%");
        });
    }

    // Accessors & Mutators
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'new' => 'blue',
            'contacted' => 'yellow',
            'qualified' => 'green',
            'converted' => 'purple',
            'lost' => 'red',
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
            'lost' => 'Lost',
            default => 'Unknown',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return ucfirst($this->priority);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: ($this->email ?: 'Anonymous Lead');
    }

    public function getInitialsAttribute(): string
    {
        if ($this->name) {
            return collect(explode(' ', $this->name))
                ->map(fn ($word) => strtoupper(substr($word, 0, 1)))
                ->take(2)
                ->implode('');
        }

        if ($this->email) {
            return strtoupper(substr($this->email, 0, 1));
        }

        return '?';
    }

    // Methods
    public function updateStatus(string $newStatus, ?User $user = null): void
    {
        $oldStatus = $this->status;

        $this->update([
            'status' => $newStatus,
            $newStatus.'_at' => now(),
            'last_activity_at' => now(),
        ]);

        // Log activity
        LeadActivity::log(
            $this,
            'status_change',
            "Status changed from {$oldStatus} to {$newStatus}",
            null,
            ['old_status' => $oldStatus, 'new_status' => $newStatus],
            $user
        );
    }

    public function updatePipelineStage(string $newStage, ?User $user = null): void
    {
        $oldStage = $this->pipeline_stage;

        $this->update([
            'pipeline_stage' => $newStage,
            'last_activity_at' => now(),
        ]);

        // Log activity
        LeadActivity::log(
            $this,
            'stage_change',
            "Pipeline stage changed from {$oldStage} to {$newStage}",
            null,
            ['old_stage' => $oldStage, 'new_stage' => $newStage],
            $user
        );
    }

    public function assignTo(User $user, ?User $assignedBy = null): void
    {
        $oldAssignee = $this->assignedTo;

        $this->update([
            'assigned_to' => $user->id,
            'last_activity_at' => now(),
        ]);

        // Log activity
        LeadActivity::log(
            $this,
            'assignment_change',
            "Lead assigned to {$user->name}",
            $oldAssignee ? "Previously assigned to {$oldAssignee->name}" : 'Previously unassigned',
            ['old_assignee_id' => $oldAssignee?->id, 'new_assignee_id' => $user->id],
            $assignedBy
        );
    }

    public function addNote(string $note, ?User $user = null): void
    {
        $this->update([
            'notes' => $this->notes ? $this->notes."\n\n".$note : $note,
            'last_activity_at' => now(),
        ]);

        // Log activity
        LeadActivity::log(
            $this,
            'note_added',
            'Note added',
            $note,
            null,
            $user
        );
    }

    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];

        if (! in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $tags = array_filter($tags, fn ($t) => $t !== $tag);
        $this->update(['tags' => array_values($tags)]);
    }

    public function scheduleFollowUp(\DateTime $date, string $type = 'follow_up', ?string $notes = null): void
    {
        $this->update([
            'next_follow_up' => $date,
            'follow_up_type' => $type,
            'follow_up_notes' => $notes,
            'last_activity_at' => now(),
        ]);

        // Log activity
        LeadActivity::log(
            $this,
            'follow_up_scheduled',
            "Follow-up scheduled for {$date->format('M j, Y \a\t g:i A')}",
            $notes,
            ['follow_up_date' => $date->toISOString(), 'follow_up_type' => $type]
        );
    }

    public function isOverdue(): bool
    {
        return $this->next_follow_up &&
               $this->next_follow_up->isPast() &&
               ! in_array($this->status, ['converted', 'lost']);
    }

    public function hasRecentActivity(int $days = 7): bool
    {
        return $this->last_activity_at &&
               $this->last_activity_at->isAfter(now()->subDays($days));
    }

    public function getScoreColor(): string
    {
        $score = $this->score ?? 0;

        return match (true) {
            $score >= 80 => 'green',
            $score >= 60 => 'blue',
            $score >= 40 => 'yellow',
            $score >= 20 => 'orange',
            default => 'red',
        };
    }
}
