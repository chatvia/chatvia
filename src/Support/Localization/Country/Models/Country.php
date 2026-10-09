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
        $name = Locale::getDisplayRegion('-'.$this->code, $locale ?? app()->getLocale());

        return $name !== '' && strcasecmp($name, $this->code) !== 0 ? $name : $this->name;
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
