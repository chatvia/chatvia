<script setup lang="ts">
// Instagram slide-over (OAuth via Facebook). Same shape as Messenger: a
// full-page hop to Facebook consent → our callback drops the IG-linked accounts
// into `pick` → the workspace selects one here.
import { Button } from '@/shared/ui/button';
import { RadioGroup, RadioGroupItem } from '@/shared/ui/radio-group';
import { redirect as redirectToInstagram, select as selectInstagramAccount } from '@/routes/agents/channels/instagram';
import { useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Plug } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import ChannelPanel from './ChannelPanel.vue';
import ConnectedSummary from './ConnectedSummary.vue';
import type { ChannelAgent, InstagramChannelProps } from './types';

const props = defineProps<{
    agent: ChannelAgent;
    instagram: InstagramChannelProps;
    canManage: boolean;
}>();

const emit = defineEmits<{ disconnect: [] }>();

const { t } = useI18n();

const page = usePage();
const error = computed<string | undefined>(() => (page.props.errors as Record<string, string> | undefined)?.instagram);

const connect = () => {
    window.location.href = redirectToInstagram.url(props.agent.external_id);
};

const selectForm = useForm({ ig_id: props.instagram.pick?.[0]?.ig_id ?? '' });

const selectAccount = () => {
    selectForm.post(selectInstagramAccount.url(props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.success(t('agents.channels.instagram.connected_toast'));
        },
    });
};
</script>

<template>
    <ChannelPanel
        :agent="agent"
        :connected="instagram.connected"
        :can-manage="canManage"
        :channel-name="t('agents.channels.setup.names.instagram')"
        @disconnect="emit('disconnect')"
    >
        <template #connected>
            <ConnectedSummary>@{{ instagram.username }}</ConnectedSummary>
        </template>

        <template #setup>
            <div v-if="error" class="border-destructive/30 bg-destructive/10 text-destructive rounded-md border p-3 text-xs">
                {{ error }}
            </div>

            <!-- Account picker — the IG accounts Facebook handed back after consent -->
            <template v-if="instagram.pick && instagram.pick.length">
                <p class="text-sm font-medium">{{ t('agents.channels.setup.instagram.pick_title') }}</p>
                <RadioGroup v-model="selectForm.ig_id" class="gap-2">
                    <label
                        v-for="igAccount in instagram.pick"
                        :key="igAccount.ig_id"
                        class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition"
                        :class="selectForm.ig_id === igAccount.ig_id ? 'border-foreground/40 bg-muted/50' : ''"
                    >
                        <RadioGroupItem :value="igAccount.ig_id" />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">@{{ igAccount.ig_username }}</span>
                            <span class="text-muted-foreground block truncate text-xs">{{ igAccount.page_name }}</span>
                        </span>
                    </label>
                </RadioGroup>
                <div class="border-t pt-5">
                    <Button class="w-full" :disabled="!selectForm.ig_id || selectForm.processing" @click="selectAccount">
                        <LoaderCircle v-if="selectForm.processing" class="me-2 size-4 animate-spin" />
                        <Plug v-else class="me-2 size-4" />
                        {{ t('agents.channels.setup.instagram.pick_button') }}
                    </Button>
                </div>
            </template>

            <!-- Kick off the OAuth flow -->
            <template v-else>
                <p class="text-muted-foreground text-sm">{{ t('agents.channels.setup.instagram.oauth_intro') }}</p>
                <div class="border-t pt-5">
                    <Button class="w-full" @click="connect">
                        <Plug class="me-2 size-4" />
                        {{ t('agents.channels.setup.instagram.connect_facebook') }}
                    </Button>
                </div>
            </template>
        </template>
    </ChannelPanel>
</template>
