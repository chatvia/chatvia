<?php

declare(strict_types=1);

namespace Domain\Notification\Data;

use Domain\Notification\Enums\NotificationCategory;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A person's notification switches, one set per category. Stored in
 * `users.notification_preferences`; a category missing there (or the whole
 * column being null) takes its defaults: the bell for everything, a desktop
 * alert for handoffs and security, a sound for handoffs.
 */
#[TypeScript]
class NotificationPreferencesData extends Data
{
    public function __construct(
        public CategoryPreferenceData $handoffs,
        public CategoryPreferenceData $security,
        public CategoryPreferenceData $training,
        public CategoryPreferenceData $leads,
        public CategoryPreferenceData $usage,
        public CategoryPreferenceData $reports,
    ) {}

    public static function defaults(): self
    {
        return self::fromStored(null);
    }

    /**
     * @param  array<string, mixed>|null  $stored  the column as saved
     */
    public static function fromStored(?array $stored): self
    {
        $categories = [];

        foreach (NotificationCategory::cases() as $category) {
            $saved = is_array($stored[$category->value] ?? null) ? $stored[$category->value] : [];
            $bell = (bool) ($saved['bell'] ?? true);

            $categories[$category->value] = new CategoryPreferenceData(
                bell: $bell,
                desktop: $bell && (bool) ($saved['desktop'] ?? $category->desktopByDefault()),
                sound: $bell && (bool) ($saved['sound'] ?? $category->soundByDefault()),
            );
        }

        return new self(...$categories);
    }

    public function for(NotificationCategory $category): CategoryPreferenceData
    {
        return match ($category) {
            NotificationCategory::Handoffs => $this->handoffs,
            NotificationCategory::Security => $this->security,
            NotificationCategory::Training => $this->training,
            NotificationCategory::Leads => $this->leads,
            NotificationCategory::Usage => $this->usage,
            NotificationCategory::Reports => $this->reports,
        };
    }
}
