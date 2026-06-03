<?php

namespace Support\Traits;

use App\Security\HMAC;
use Illuminate\Support\Str;

/**
 * @property string|null $external_id
 * @property array<int, string> $hmacEncryptionColumns
 */
trait SupportsExternalId
{
    /**
     * Named `bootSupportsExternalId` rather than `booted` so models using
     * this trait can still override `booted()` themselves (e.g. for
     * cascade deletes) without shadowing the auto-generated external_id
     * hook. Laravel auto-invokes any `boot<TraitName>` method during model
     * boot.
     */
    protected static function bootSupportsExternalId(): void
    {
        static::creating(function ($model): void {
            if (! $model->external_id) {
                $model->external_id = Str::uuid7()->toString();
            }
        });
    }

    public function validateEncryptedData(string $validationString): bool
    {
        $validationString = app(HMAC::class)->decrypt($validationString);

        return $validationString === $this->prepareDataForEncryption();
    }

    public function prepareDataForEncryption(): string
    {
        $this->loadHmacRelationships();

        $str = '';
        $total = count($this->hmacEncryptionColumns);
        foreach ($this->hmacEncryptionColumns as $idx => $column) {
            $value = data_get($this, $column);
            $str .= $value.(($idx + 1 !== $total) ? ':' : '');
        }

        return $str;
    }

    protected function loadHmacRelationships(): void
    {
        $relationships = collect($this->hmacEncryptionColumns)
            ->filter(fn ($column): bool => str_contains($column, '.'))
            ->map(function ($column) {
                $parts = explode('.', $column);
                array_pop($parts);

                return implode('.', $parts);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($relationships)) {
            $this->load($relationships);
        }
    }

    public function encrypt(): string
    {
        return app(HMAC::class)->encrypt($this->prepareDataForEncryption());
    }
}
