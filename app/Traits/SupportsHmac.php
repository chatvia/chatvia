<?php

namespace App\Traits;

use App\Services\Security\HMAC;
use Illuminate\Support\Str;

trait SupportsHmac
{
    protected static function booted(): void
    {
        static::creating(function ($model) {
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
            ->filter(fn ($column) => str_contains($column, '.'))
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
