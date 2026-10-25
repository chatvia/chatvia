<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\WidgetSettings;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * What the Chat Widget shows on every agent: whether its header says the
 * visitor is talking to an AI assistant.
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

    public static function description(): string
    {
        return 'What the Chat Widget shows on every agent.';
    }

    public static function overviewGroup(): string
    {
        return 'Platform and brand';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('ai_disclosure', 'AI disclosure', 'Whether the widget tells visitors they are talking to an AI.', Heroicon::OutlinedSparkles, fn (): array => [
                $this->toggle('ai_disclosure', 'Show "AI assistant" in the header',
                    help: 'The line under the chat title. The EU AI Act (Article 50) asks you to tell people they are talking to an AI, so turn it off only if you tell them another way. "Human agent" still shows while a person answers.'),
            ])->summary(fn (): string => $this->valueInUse('ai_disclosure') ? 'Shows "AI assistant"' : 'Hides "AI assistant"'),
        ];
    }
}
