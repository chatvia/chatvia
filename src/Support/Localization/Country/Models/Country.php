<?php

declare(strict_types=1);

namespace Support\Localization\Country\Models;

use Collator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Locale;
use Support\Localization\Country\Data\CountryOptionData;

/**
 * Reference data: a country (`countries` table, keyed by ISO code). Visitor
 * sessions point at it by `country_id`; country pickers list it.
 *
 * The class name is imported by a shipped migration, so it must not move.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Country whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Country extends Model
{
    /**
     * The country's name in the given language (the app locale by default),
     * from the ICU data of PHP's intl extension. The stored English name is
     * the fallback for a code ICU does not know.
     */
    public function localizedName(?string $locale = null): string
    {
        $name = Locale::getDisplayRegion("-{$this->code}", $locale ?? app()->getLocale());

        return $name !== '' && strcasecmp($name, $this->code) !== 0 ? $name : $this->name;
    }

    /**
     * The ISO code of the country with this id, or null. The table is small
     * and never changes while the app runs, so it is read once per process:
     * a hot path (every stored chat message) needs no query of its own.
     */
    public static function codeFor(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        /** @var array<int, string> $codes */
        $codes = once(static fn (): array => static::query()->pluck('code', 'id')->all());

        return $codes[$id] ?? null;
    }

    /**
     * Every country as a picker option, named and sorted in the app locale.
     *
     * @return list<CountryOptionData>
     */
    public static function options(): array
    {
        $collator = new Collator(app()->getLocale());

        $options = static::query()
            ->select('code', 'name')
            ->get()
            ->map(fn (Country $country): CountryOptionData => new CountryOptionData($country->code, $country->localizedName()))
            ->all();

        usort($options, fn (CountryOptionData $a, CountryOptionData $b): int => (int) $collator->compare($a->name, $b->name));

        return $options;
    }
}
