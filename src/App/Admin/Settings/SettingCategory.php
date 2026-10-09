<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Filament\Support\Icons\Heroicon;

/**
 * The tabs of the admin Settings page.
 */
enum SettingCategory: string
{
    case SignIn = 'sign-in';
    case Channels = 'channels';
    case Ai = 'ai';
    case Knowledge = 'knowledge';
    case Connectors = 'connectors';
    case Billing = 'billing';
    case Platform = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::SignIn => 'Sign-in',
            self::Channels => 'Channels',
            self::Ai => 'AI providers',
            self::Knowledge => 'Knowledge',
            self::Connectors => 'Connectors',
            self::Billing => 'Billing',
            self::Platform => 'Platform',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SignIn => 'OAuth apps behind the "Sign in with …" buttons on the login and register pages.',
            self::Channels => 'One Meta app for Messenger, Instagram and WhatsApp. Telegram, Slack and Teams are set per agent.',
            self::Ai => 'API keys for chat, embeddings, reranking and speech to text.',
            self::Knowledge => 'Services that import websites and split documents into chunks.',
            self::Connectors => 'OAuth apps for connectors that do not support dynamic client registration.',
            self::Billing => 'Stripe keys for plans, add-ons and credit top-ups.',
            self::Platform => 'Email delivery, bot protection and visitor location.',
        };
    }

    public function icon(): Heroicon
    {
        return match ($this) {
            self::SignIn => Heroicon::OutlinedFingerPrint,
            self::Channels => Heroicon::OutlinedChatBubbleLeftRight,
            self::Ai => Heroicon::OutlinedSparkles,
            self::Knowledge => Heroicon::OutlinedBookOpen,
            self::Connectors => Heroicon::OutlinedPuzzlePiece,
            self::Billing => Heroicon::OutlinedCreditCard,
            self::Platform => Heroicon::OutlinedServerStack,
        };
    }

    /**
     * @return list<SettingGroup>
     */
    public function groups(): array
    {
        return array_values(array_filter(
            SettingGroup::cases(),
            fn (SettingGroup $group): bool => $group->category() === $this,
        ));
    }
}
