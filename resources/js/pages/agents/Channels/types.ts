/**
 * Shapes of the props the Channels page receives (built by
 * `AgentChannelsController`) and of the cards it renders. Shared by the page,
 * its per-channel panels and their composables so they all agree on one shape.
 */

export type ChannelKind = 'telegram' | 'slack' | 'whatsapp' | 'messenger' | 'msteams' | 'instagram';

export interface ChannelAgent {
    external_id: string;
    name: string;
    is_public: boolean;
}

export interface TelegramCommand {
    command: string;
    description: string;
    type: 'message' | 'prompt';
    value: string;
    builtin: boolean;
}

export interface TelegramChannelProps {
    connected: boolean;
    bot_username: string | null;
    enabled: boolean;
    commands: TelegramCommand[];
}

export interface SlackChannelProps {
    connected: boolean;
    team_name: string | null;
    webhook_url: string | null;
    enabled: boolean;
}

export interface MessengerChannelProps {
    connected: boolean;
    page_name: string | null;
    enabled: boolean;
    pick: { id: string; name: string }[] | null;
}

export interface WhatsappChannelProps {
    connected: boolean;
    display_phone_number: string | null;
    verified_name: string | null;
    enabled: boolean;
    app_id: string | null;
    config_id: string | null;
    graph_version: string;
}

export interface MsTeamsChannelProps {
    connected: boolean;
    app_id: string | null;
    webhook_url: string | null;
    enabled: boolean;
}

export interface InstagramChannelProps {
    connected: boolean;
    username: string | null;
    enabled: boolean;
    pick: { ig_id: string; ig_username: string; page_name: string }[] | null;
}

export interface ChannelsPageProps {
    agent: ChannelAgent;
    telegram: TelegramChannelProps;
    slack: SlackChannelProps;
    messenger: MessengerChannelProps;
    whatsapp: WhatsappChannelProps;
    msteams: MsTeamsChannelProps;
    instagram: InstagramChannelProps;
    // Channel slide-over to auto-open on load — set after the Messenger/Instagram
    // OAuth round-trip (a full-page redirect) so the picker/result is visible
    // immediately instead of behind a closed sheet.
    open: ChannelKind | null;
}

/** One card on the Channels grid (and the header of its slide-over). */
export interface ChannelItem {
    kind: ChannelKind;
    name: string;
    tagline: string;
    logo: string;
    badge?: { label: string; variant: 'default' | 'secondary' | 'outline' };
}
