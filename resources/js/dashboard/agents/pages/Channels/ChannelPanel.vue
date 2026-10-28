<script setup lang="ts">
// The state machine every channel slide-over shares:
//   connected      → the channel's details (#connected), the private-agent
//                    banner and the Disconnect button;
//   protected agent → messaging channels are public-only, link to Security;
//   can manage     → the channel's connect steps / form (#setup);
//   otherwise      → a no-permission note.
// Each channel component only fills in the two slots.
import { Button } from '@/shared/ui/button';
import { identity } from '@/routes/agents';
import { Link } from '@inertiajs/vue3';
import { Unplug } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import type { ChannelAgent } from './types';

defineProps<{
    agent: ChannelAgent;
    connected: boolean;
    canManage: boolean;
    /** Translated channel name, used in the banners. */
    channelName: string;
}>();

const emit = defineEmits<{ disconnect: [] }>();

const { t } = useI18n();
</script>

<template>
    <!-- Connected -->
    <template v-if="connected">
        <slot name="connected" />

        <div v-if="!agent.is_public" class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400">
            {{ t('agents.channels.private_banner', { channel: channelName }) }}
        </div>
        <div v-if="canManage" class="border-t pt-5">
            <Button variant="outline" class="text-destructive hover:text-destructive w-full" @click="emit('disconnect')">
                <Unplug class="me-2 size-4" />
                {{ t('agents.channels.disconnect') }}
            </Button>
        </div>
    </template>

    <!-- Protected agent: messaging channels are public-only -->
    <template v-else-if="!agent.is_public">
        <div class="bg-muted/40 rounded-lg border border-dashed p-5 text-center">
            <p class="text-sm font-medium">{{ t('agents.channels.private_title') }}</p>
            <p class="text-muted-foreground mt-1 text-sm">
                <i18n-t keypath="agents.channels.setup.public_only" tag="span" scope="global">
                    <template #channel>{{ channelName }}</template>
                    <template #security>
                        <Link
                            :href="identity.url(agent.external_id)"
                            class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                            >{{ t('agents.channels.security') }}</Link
                        >
                    </template>
                </i18n-t>
            </p>
        </div>
    </template>

    <!-- Not connected -->
    <template v-else-if="canManage">
        <slot name="setup" />
    </template>

    <template v-else>
        <p class="text-muted-foreground text-sm">{{ t('agents.channels.no_permission') }}</p>
    </template>
</template>
