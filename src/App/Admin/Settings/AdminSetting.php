<?php

namespace App\Admin\Settings;

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * The credentials saved in the admin panel for one settings group. Every value
 * of the group lives in `payload`, encrypted as one JSON blob. Read them through
 * config (see {@see SettingsStore}), not from this model.
 *
 * @property int $id
 * @property SettingGroup $key
 * @property array<string, string>|null $payload
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $updatedBy
 */
#[Guarded(['id'])]
#[RouteKey('key')]
class AdminSetting extends Model
{
    /**
     * Saved settings are cached for every request, so any write clears the
     * cache and the new values apply on the next request (or queued job).
     */
    protected static function booted(): void
    {
        $forget = fn (): bool => app(SettingsStore::class)->forget();

        static::saved($forget);
        static::deleted($forget);
    }

    public static function forGroup(SettingGroup $group): self
    {
        return static::query()->firstOrCreate(['key' => $group], ['payload' => null, 'updated_by' => null]);
    }

    protected function casts(): array
    {
        return [
            'key' => SettingGroup::class,
            'payload' => 'encrypted:array',
        ];
    }

    public function getRouteKey(): string
    {
        return $this->key->value;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function value(SettingField $field): ?string
    {
        $value = $this->payload[$field->name] ?? null;

        return filled($value) ? (string) $value : null;
    }

    public function hasSavedValues(): bool
    {
        return filled($this->payload);
    }

    /**
     * The payload after an edit in the panel. Secrets are never sent to the
     * browser, so a blank secret means "unchanged" and keeps its saved value.
     * Any other blank field is removed.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>|null
     */
    public function payloadFrom(array $input): ?array
    {
        $payload = [];

        foreach ($this->key->fields() as $field) {
            $value = trim((string) ($input[$field->name] ?? ''));

            if ($value === '' && $field->secret) {
                $value = (string) $this->value($field);
            }

            if ($value !== '') {
                $payload[$field->name] = $value;
            }
        }

        return $payload === [] ? null : $payload;
    }

    /**
     * Remove one saved value.
     */
    public function forgetValue(SettingField $field, ?User $by): void
    {
        $payload = Arr::except($this->payload ?? [], $field->name);

        $this->update([
            'payload' => $payload === [] ? null : $payload,
            'updated_by' => $by?->id,
        ]);
    }
}
