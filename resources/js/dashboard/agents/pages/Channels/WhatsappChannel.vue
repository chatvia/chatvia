<script setup lang="ts">
// WhatsApp slide-over. The Embedded Signup flow itself lives in the page's
// useWhatsappEmbeddedSignup (it must outlive this panel and react to the sheet
// closing); this component only shows its state and asks it to connect.
import { Button } from '@/shared/ui/button';
import { usePage } from '@inertiajs/vue3';
import { LoaderCircle, Plug } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ChannelPanel from './ChannelPanel.vue';
import ConnectedSummary from './ConnectedSummary.vue';
import type { ChannelAgent, WhatsappChannelProps } from './types';

defineProps<{
    agent: ChannelAgent;
    whatsapp: WhatsappChannelProps;
    canManage: boolean;
    /** An Embedded Signup popup is in flight. */
    connecting: boolean;
}>();

const emit = defineEmits<{ disconnect: []; connect: [] }>();

const { t } = useI18n();

const page = usePage();
const error = computed<string | undefined>(() => (page.props.errors as Record<string, string> | undefined)?.whatsapp);
</script>

<template>
    <ChannelPanel
        :agent="agent"
        :connected="whatsapp.connected"
        :can-manage="canManage"
        :channel-name="t('agents.channels.setup.names.whatsapp')"
        @disconnect="emit('disconnect')"
    >
        <template #connected>
            <ConnectedSummary>
                {{ whatsapp.verified_name ?? whatsapp.display_phone_number }}
                <template v-if="whatsapp.verified_name && whatsapp.display_phone_number"> · {{ whatsapp.display_phone_number }} </template>
            </ConnectedSummary>
        </template>

        <template #setup>
            <div v-if="error" class="border-destructive/30 bg-destructive/10 text-destructive rounded-md border p-3 text-xs">
                {{ error }}
            </div>

            <p class="text-muted-foreground text-sm">{{ t('agents.channels.setup.whatsapp.oauth_intro') }}</p>
            <div class="border-t pt-5">
                <Button class="w-full" :disabled="connecting" @click="emit('connect')">
                    <LoaderCircle v-if="connecting" class="me-2 size-4 animate-spin" />
                    <Plug v-else class="me-2 size-4" />
                    {{ t('agents.channels.setup.whatsapp.connect_facebook') }}
                </Button>
            </div>
        </template>
    </ChannelPanel>
</template>
