<script setup lang="ts">
// Messenger slide-over (Facebook Login for Business). Connecting is a full-page
// hop to Facebook's consent screen, not an Inertia XHR: redirect → consent → our
// callback drops the granted Pages into `pick` → the workspace picks one here.
// Callback errors come back as a shared page error (no form owns them).
import { Button } from '@/components/ui/button';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { redirect as redirectToMessenger, select as selectMessengerPage } from '@/routes/agents/channels/messenger';
import { useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Plug } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import ChannelPanel from './ChannelPanel.vue';
import ConnectedSummary from './ConnectedSummary.vue';
import type { ChannelAgent, MessengerChannelProps } from './types';

const props = defineProps<{
    agent: ChannelAgent;
    messenger: MessengerChannelProps;
    canManage: boolean;
}>();

const emit = defineEmits<{ disconnect: [] }>();

const { t } = useI18n();

const page = usePage();
const error = computed<string | undefined>(() => (page.props.errors as Record<string, string> | undefined)?.messenger);

const connect = () => {
    window.location.href = redirectToMessenger.url(props.agent.external_id);
};

const selectForm = useForm({ page_id: props.messenger.pick?.[0]?.id ?? '' });

const selectPage = () => {
    selectForm.post(selectMessengerPage.url(props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.success(t('agents.channels.messenger.connected_toast'));
        },
    });
};
</script>

<template>
    <ChannelPanel
        :agent="agent"
        :connected="messenger.connected"
        :can-manage="canManage"
        :channel-name="t('agents.channels.setup.names.messenger')"
        @disconnect="emit('disconnect')"
    >
        <template #connected>
            <ConnectedSummary>{{ messenger.page_name }}</ConnectedSummary>
        </template>

        <template #setup>
            <div v-if="error" class="border-destructive/30 bg-destructive/10 text-destructive rounded-md border p-3 text-xs">
                {{ error }}
            </div>

            <!-- Page picker — the Pages Facebook handed back after consent -->
            <template v-if="messenger.pick && messenger.pick.length">
                <p class="text-sm font-medium">{{ t('agents.channels.setup.messenger.pick_title') }}</p>
                <RadioGroup v-model="selectForm.page_id" class="gap-2">
                    <label
                        v-for="fbPage in messenger.pick"
                        :key="fbPage.id"
                        class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition"
                        :class="selectForm.page_id === fbPage.id ? 'border-foreground/40 bg-muted/50' : ''"
                    >
                        <RadioGroupItem :value="fbPage.id" />
                        <span class="truncate text-sm font-medium">{{ fbPage.name }}</span>
                    </label>
                </RadioGroup>
                <div class="border-t pt-5">
                    <Button class="w-full" :disabled="!selectForm.page_id || selectForm.processing" @click="selectPage">
                        <LoaderCircle v-if="selectForm.processing" class="me-2 size-4 animate-spin" />
                        <Plug v-else class="me-2 size-4" />
                        {{ t('agents.channels.setup.messenger.pick_button') }}
                    </Button>
                </div>
            </template>

            <!-- Kick off the OAuth flow -->
            <template v-else>
                <p class="text-muted-foreground text-sm">{{ t('agents.channels.setup.messenger.oauth_intro') }}</p>
                <div class="border-t pt-5">
                    <Button class="w-full" @click="connect">
                        <Plug class="me-2 size-4" />
                        {{ t('agents.channels.setup.messenger.connect_facebook') }}
                    </Button>
                </div>
            </template>
        </template>
    </ChannelPanel>
</template>
