<?php

declare(strict_types=1);

namespace App\Admin\Issue\Models;

use App\Admin\Issue\Data\IssueActionData;
use App\Admin\Issue\Data\IssueServiceData;
use App\Admin\Issue\Data\IssueSubject;
use App\Admin\Issue\Enums\IssueResolution;
use App\Admin\Issue\Enums\IssueStatus;
use App\Admin\Issue\Services\IssueServices;
use App\Admin\Issue\Services\IssueTypes;
use App\Admin\Issue\Types\IssueType;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Support\Concerns\HasExternalId;

/**
 * An issue an admin can fix, on Radar (the admin panel's issues page): one
 * service and one kind of issue ("fal.ai refused the API key"; a
 * per-subject kind also by its subject: a gone model, a failed job's class,
 * a workspace at its limit). `service` is a service key
 * ({@see IssueServices}), `kind` an issue type key ({@see IssueTypes}).
 * Each time it happens is an {@see IssueOccurrence}. `message` is what the
 * service said last. A resolved issue was closed by an admin or by the
 * service working again (`resolution`).
 *
 * @property int $id
 * @property string $external_id
 * @property string $fingerprint
 * @property string $service
 * @property string $kind
 * @property string|null $subject
 * @property IssueStatus $status
 * @property IssueResolution|null $resolution
 * @property string|null $message
 * @property int $occurrences_count
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasExternalId, HasFactory;

    /** What makes two reports the same issue: the service, the kind and, for a per-subject kind, the subject. */
    public static function fingerprint(string $service, string $kind, ?string $subject): string
    {
        return Str::of(collect([$service, $kind, $subject ?? ''])->join('|'))
            ->hash('sha1')
            ->toString();
    }

    /**
     * @return HasMany<IssueOccurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(IssueOccurrence::class);
    }

    /**
     * @return HasOne<IssueOccurrence, $this>
     */
    public function latestOccurrence(): HasOne
    {
        return $this->hasOne(IssueOccurrence::class)->latestOfMany('occurred_at');
    }

    /** Its kind; null for a kind no longer listed in config/radar.php. */
    public function type(): ?IssueType
    {
        return app(IssueTypes::class)->for($this->kind);
    }

    public function serviceData(): IssueServiceData
    {
        return app(IssueServices::class)->for($this->service);
    }

    /** "fal.ai refused the API key". */
    public function title(): string
    {
        return $this->type()?->title($this->subject()) ?? "{$this->serviceData()->label}: {$this->kind}";
    }

    public function advice(): ?string
    {
        return $this->type()?->advice($this->subject());
    }

    /** What a per-subject issue is about, as people read it: a model, a job, a workspace's name. */
    public function subjectLabel(): ?string
    {
        return $this->subject === null ? null : ($this->type()?->subjectLabel($this->subject) ?? $this->subject);
    }

    /** @return list<IssueActionData> */
    public function actions(): array
    {
        return $this->type()?->actions($this->subject()) ?? [];
    }

    private function subject(): IssueSubject
    {
        return new IssueSubject($this->serviceData(), $this->subject);
    }

    public function resolve(): void
    {
        $this->update(['status' => IssueStatus::Resolved, 'resolution' => IssueResolution::Admin, 'resolved_at' => now()]);
    }

    public function ignore(): void
    {
        $this->update(['status' => IssueStatus::Ignored, 'resolution' => null, 'resolved_at' => null]);
    }

    public function reopen(): void
    {
        $this->update(['status' => IssueStatus::Open, 'resolution' => null, 'resolved_at' => null]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IssueStatus::class,
            'resolution' => IssueResolution::class,
            'occurrences_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
