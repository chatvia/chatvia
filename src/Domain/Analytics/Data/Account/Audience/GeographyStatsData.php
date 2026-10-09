<?php

namespace Domain\Analytics\Data\Account\Audience;

use Domain\Analytics\Data\Account\LabelCountData;

/**
 * Where visitors come from and what they chat on, over a window.
 */
final readonly class GeographyStatsData
{
    /**
     * @param  list<CountryShareData>  $countries  Most conversations first.
     * @param  list<LabelCountData>  $devices
     * @param  list<LabelCountData>  $browsers
     * @param  list<LabelCountData>  $operatingSystems
     */
    public function __construct(
        public array $countries,
        public array $devices,
        public array $browsers,
        public array $operatingSystems,
    ) {}
}
