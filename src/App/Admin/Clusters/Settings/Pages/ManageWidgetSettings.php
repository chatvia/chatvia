<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\WidgetSettings;
use BackedEnum;
use Domain\Widget\Enums\ChatDeletionMode;
use Filament\Support\Icons\Heroicon;

/**
 * The Chat Widget on every agent: where it may load, where its script comes
 * from, how many replies it may stream, whether its header says the visitor
 * is talking to an AI assistant, and what a visitor's "remove this chat" does.
 */
class ManageWidgetSettings extends AdminSettingsPage
{
    #[\Override]
    protected static string $settings = WidgetSettings::class;

    #[\Override]
    protected static ?string $title = 'Widget';

    #[\Override]
    protected static ?string $slug = 'widget';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleOvalLeft;

    #[\Override]
    protected static ?int $navigationSort = 17;

    /** The "Removing chats" choices, by stored value. */
    private const array CHAT_DELETION_MODES = [
        'end' => 'End chat only',
        'hide' => 'Remove from history',
        'delete' => 'Delete permanently',
    ];

    public static function description(): string
    {
        return 'Where the Chat Widget may load, how much it may be used, and what it shows.';
    }

    public static function overviewGroup(): string
    {
        return 'Platform and brand';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('sites', 'Sites and limits', 'Where the widget may load, and how much it may be used.', Heroicon::OutlinedGlobeAlt, fn (): array => [
                $this->text('allowed_origins', 'Sites every widget may load on',
                    help: 'Extra websites where any agent\'s widget may load, on top of each agent\'s own sites. Separate them with commas, such as "example.com, shop.example.com".'),
            ])->advancedFields(fn (): array => [
                $this->urlOrPath('script_url', 'Widget script address',
                    help: 'Where the widget\'s code loads from. Leave blank to use the copy on this server, or enter a full address to load it from elsewhere.'),
                $this->integer('stream_per_minute', 'Replies per agent per minute', min: 1),
                $this->integer('stream_per_hour', 'Replies per agent per hour', min: 1),
            ])->summary(fn (): string => $this->valueInUse('stream_per_minute').' replies per agent per minute'),
            SettingsCard::make('ai_disclosure', 'AI disclosure', 'Whether the widget tells visitors they are talking to an AI.', Heroicon::OutlinedSparkles, fn (): array => [
                $this->toggle('ai_disclosure', 'Show "AI assistant" in the header',
                    help: 'The line under the chat title. The EU AI Act (Article 50) asks you to tell people they are talking to an AI, so turn it off only if you tell them another way. "Human agent" still shows while a person answers.'),
            ])->summary(fn (): string => $this->valueInUse('ai_disclosure') ? 'Shows "AI assistant"' : 'Hides "AI assistant"'),
            SettingsCard::make('chat_deletion', 'Removing chats', 'What happens when a visitor removes a chat from the widget.', Heroicon::OutlinedTrash, fn (): array => [
                $this->choice('chat_deletion_mode', 'When a visitor removes a chat', self::CHAT_DELETION_MODES,
                    help: 'End chat only keeps every chat. Remove from history hides it from the visitor and keeps it in the chat log. Delete permanently erases it for good.'),
            ])->summary(fn (): string => self::CHAT_DELETION_MODES[ChatDeletionMode::inUse()->value]),
        ];
    }
}
