<?php

namespace App\Marketing\Enums;

use App\Marketing\Models\ChangelogItem;

/**
 * The groups a changelog entry can belong to ({@see ChangelogItem}::$type),
 * with how the public changelog page shows each group.
 */
enum ChangelogChangeType: string
{
    case NewFeatures = 'new_features';
    case Improvements = 'improvements';
    case BugFixes = 'bug_fixes';
    case BreakingChanges = 'breaking_changes';
    case CoreFeatures = 'core_features';

    public function label(): string
    {
        return match ($this) {
            self::NewFeatures => 'New Features',
            self::Improvements => 'Improvements',
            self::BugFixes => 'Bug Fixes',
            self::BreakingChanges => 'Breaking Changes',
            self::CoreFeatures => 'Core Features',
        };
    }

    /** Tailwind colour name for the group's dot and bullets. */
    public function color(): string
    {
        return match ($this) {
            self::NewFeatures, self::CoreFeatures => 'green',
            self::Improvements => 'blue',
            self::BugFixes => 'yellow',
            self::BreakingChanges => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NewFeatures, self::CoreFeatures => '✓',
            self::Improvements => '↑',
            self::BugFixes => '🔧',
            self::BreakingChanges => '⚠️',
        };
    }
}
