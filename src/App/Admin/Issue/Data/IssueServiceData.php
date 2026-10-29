<?php

declare(strict_types=1);

namespace App\Admin\Issue\Data;

use App\Admin\Clusters\Settings\IconTone;
use App\Admin\Issue\Services\IssueServices;
use Domain\Llm\Enums\AiProvider;
use Filament\Support\Icons\Heroicon;
use Spatie\LaravelData\Data;

/**
 * A service as Radar shows it: its name, its logo (an image) or else its
 * icon in the colour of its tone (as Settings colours its tiles,
 * {@see IconTone}), the admin panel page
 * where it is set up and, for an AI provider,
 * where its keys are made
 * ({@see IssueServices}).
 */
class IssueServiceData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $logo,
        public Heroicon $icon,
        public string $tone,
        public ?string $settingsUrl,
        public ?string $keysUrl,
        public ?AiProvider $provider,
    ) {}
}
