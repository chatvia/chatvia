<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings;

use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The colour of an icon's tile in Settings (the overview tiles, the card
 * headers, the search finds and the page outline), so Settings looks like
 * the Status page. The colour follows the icon, so the same icon has the
 * same colour on every page; a service logo keeps its own colours.
 */
final class IconTone
{
    /** The tone of a logo, which keeps its own colours on a light tile. */
    public const string LOGO = 'logo';

    /** Icon names (without the Outlined/Mini/Micro prefix) by tone. */
    private const array TONES = [
        'violet' => ['Sparkles', 'CubeTransparent', 'Beaker', 'LightBulb'],
        'sky' => ['Cloud', 'GlobeAlt', 'ArrowRightEndOnRectangle', 'ArchiveBox', 'Signal', 'ArrowsRightLeft', 'MapPin'],
        'amber' => ['CpuChip', 'Bolt', 'Clock', 'Bell', 'BellAlert', 'Key', 'LockClosed', 'Squares2x2'],
        'rose' => ['Envelope', 'Heart', 'ShieldExclamation'],
        'emerald' => ['ShieldCheck', 'CheckBadge', 'Banknotes', 'ChartBar', 'UserPlus'],
        'indigo' => ['ServerStack', 'ChatBubbleLeftRight', 'ChatBubbleOvalLeftEllipsis', 'Language', 'SpeakerWave', 'Microphone'],
        'teal' => ['BookOpen', 'DocumentMagnifyingGlass', 'DocumentDuplicate', 'DocumentArrowDown', 'ClipboardDocumentList', 'MagnifyingGlass', 'Scissors', 'BarsArrowDown'],
        'orange' => ['CodeBracket', 'CodeBracketSquare', 'CommandLine', 'WrenchScrewdriver', 'Cog6Tooth', 'Cog8Tooth', 'AdjustmentsHorizontal', 'PuzzlePiece'],
        'fuchsia' => ['Swatch', 'Tag', 'Briefcase', 'BuildingOffice2'],
    ];

    /** An icon no tone names is grey. */
    private const string DEFAULT = 'slate';

    /**
     * The tone of an icon: a colour name, or {@see self::LOGO} for a logo's
     * URL. Anything else (an icon given by name, no icon) is grey.
     */
    public static function of(mixed $icon): string
    {
        if (! $icon instanceof Heroicon && filled($icon) && Str::contains($icon, '/')) {
            return self::LOGO;
        }

        if (! $icon instanceof Heroicon) {
            return self::DEFAULT;
        }

        $name = Str::replaceMatches('/^(Outlined|Mini|Micro)/', '', $icon->name) ?? '';

        foreach (self::TONES as $tone => $names) {
            if (in_array($name, $names, true)) {
                return $tone;
            }
        }

        return self::DEFAULT;
    }

    /** The CSS class that colours the icon's tile. */
    public static function cssClass(mixed $icon): string
    {
        return 'cv-tone-'.self::of($icon);
    }
}
