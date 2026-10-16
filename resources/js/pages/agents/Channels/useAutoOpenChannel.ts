/**
 * Re-opens the slide-over the server asked for. Messenger/Instagram land on the
 * Channels page via a full-page OAuth redirect, so the sheet would otherwise
 * start closed and hide the account picker / error. `?open=` is stripped from
 * the URL afterwards so a manual refresh doesn't keep re-opening it.
 */
import { onMounted, type Ref } from 'vue';
import type { ChannelItem, ChannelKind } from './types';

export function useAutoOpenChannel(open: () => ChannelKind | null, items: Ref<ChannelItem[]>, openChannel: (item: ChannelItem) => void): void {
    onMounted(() => {
        const kind = open();
        const item = kind ? items.value.find((i) => i.kind === kind) : undefined;
        if (!item || item.disabled) return;

        openChannel(item);

        const url = new URL(window.location.href);
        url.searchParams.delete('open');
        window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
    });
}
