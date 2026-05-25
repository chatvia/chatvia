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
    protected static function booted(): void
    {
        static::creating(function ($model): void {
            if (! $model->external_id) {
                $model->external_id = Str::uuid()->toString();
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
