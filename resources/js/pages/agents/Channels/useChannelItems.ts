/**
 * The cards shown on the Channels page: one per messaging app, with its name,
 * logo, a tagline that names the connected account, and a "Connected" badge.
 * Computed from the page props so a connect/disconnect reload updates the grid
 * and the open slide-over header together.
 *
 * Messenger, WhatsApp and Instagram run on Meta apps an admin sets up in the
 * admin panel; until then their cards are disabled. A channel that is already
 * connected stays open so it can still be disconnected.
 */
import { computed, type ComputedRef } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ChannelItem, ChannelsPageProps } from './types';

export function useChannelItems(props: ChannelsPageProps): ComputedRef<ChannelItem[]> {
    const { t } = useI18n();

    const unavailable = (channel: { available: boolean; connected: boolean }): boolean => !channel.available && !channel.connected;

    return computed<ChannelItem[]>(() => [
        {
            kind: 'telegram',
            name: t('agents.channels.setup.names.telegram'),
            tagline: props.telegram.connected
                ? t('agents.channels.telegram.tagline_connected', { username: props.telegram.bot_username })
                : t('agents.channels.telegram.tagline'),
            logo: '/images/logos/channels/telegram.svg',
            badge: props.telegram.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
        },
        {
            kind: 'slack',
            name: t('agents.channels.setup.names.slack'),
            tagline: props.slack.connected
                ? t('agents.channels.slack.tagline_connected', { team: props.slack.team_name })
                : t('agents.channels.slack.tagline'),
            logo: '/images/logos/channels/slack.svg',
            badge: props.slack.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
        },
        {
            kind: 'messenger',
            name: t('agents.channels.setup.names.messenger'),
            tagline: props.messenger.connected
                ? t('agents.channels.messenger.tagline_connected', { page: props.messenger.page_name })
                : unavailable(props.messenger)
                  ? t('agents.channels.not_set_up')
                  : t('agents.channels.messenger.tagline'),
            logo: '/images/logos/channels/messenger.svg',
            badge: props.messenger.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
            disabled: unavailable(props.messenger),
        },
        {
            kind: 'whatsapp',
            name: t('agents.channels.setup.names.whatsapp'),
            tagline: props.whatsapp.connected
                ? t('agents.channels.whatsapp.tagline_connected', { name: props.whatsapp.verified_name ?? props.whatsapp.display_phone_number })
                : unavailable(props.whatsapp)
                  ? t('agents.channels.not_set_up')
                  : t('agents.channels.whatsapp.tagline'),
            logo: '/images/logos/channels/whatsapp.svg',
            badge: props.whatsapp.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
            disabled: unavailable(props.whatsapp),
        },
        {
            kind: 'msteams',
            name: t('agents.channels.setup.names.msteams'),
            tagline: props.msteams.connected ? t('agents.channels.msteams.tagline_connected') : t('agents.channels.msteams.tagline'),
            logo: '/images/logos/channels/msteams.svg',
            badge: props.msteams.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
        },
        {
            kind: 'instagram',
            name: t('agents.channels.setup.names.instagram'),
            tagline: props.instagram.connected
                ? t('agents.channels.instagram.tagline_connected', { username: props.instagram.username })
                : unavailable(props.instagram)
                  ? t('agents.channels.not_set_up')
                  : t('agents.channels.instagram.tagline'),
            logo: '/images/logos/channels/instagram.svg',
            badge: props.instagram.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
            disabled: unavailable(props.instagram),
        },
    ]);
}
