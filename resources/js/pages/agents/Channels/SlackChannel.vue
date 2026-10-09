<script setup lang="ts">
// Slack slide-over: connect the workspace's own Slack app with its bot token +
// signing secret; once connected, show the Request URL to paste into the app's
// Event Subscriptions and the remaining setup steps.
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import { KeyRound, Link2, LoaderCircle, Lock, Plug } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import ChannelPanel from './ChannelPanel.vue';
import ConnectedSummary from './ConnectedSummary.vue';
import CopyField from './CopyField.vue';
import CredentialInput from './CredentialInput.vue';
import SetupStep from './SetupStep.vue';
import type { ChannelAgent, SlackChannelProps } from './types';

const props = defineProps<{
    agent: ChannelAgent;
    slack: SlackChannelProps;
    canManage: boolean;
}>();

const emit = defineEmits<{ disconnect: [] }>();

const { t } = useI18n();

const form = useForm({ bot_token: '', signing_secret: '' });

const connect = () => {
    form.post(route('agents.channels.slack.connect', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            toast.success(t('agents.channels.slack.connected_toast'));
        },
    });
};
</script>

<template>
    <ChannelPanel
        :agent="agent"
        :connected="slack.connected"
        :can-manage="canManage"
        :channel-name="t('agents.channels.setup.names.slack')"
        @disconnect="emit('disconnect')"
    >
        <template #connected>
            <ConnectedSummary>{{ slack.team_name }}</ConnectedSummary>

            <CopyField id="slack-webhook-url" :label="t('agents.channels.setup.labels.request_url')" :icon="Link2" :value="slack.webhook_url" />

            <ol class="text-muted-foreground space-y-2 text-xs">
                <SetupStep :step="1">
                    <i18n-t keypath="agents.channels.setup.slack.steps.connected_1" tag="span">
                        <template #link>
                            <a
                                href="https://api.slack.com/apps"
                                target="_blank"
                                rel="noopener"
                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                >{{ t('agents.channels.setup.slack.your_slack_app') }}</a
                            >
                        </template>
                        <template #event_subscriptions>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.slack.event_subscriptions') }}</span>
                        </template>
                    </i18n-t>
                </SetupStep>
                <SetupStep :step="2">
                    <i18n-t keypath="agents.channels.setup.slack.steps.connected_2" tag="span">
                        <template #mention><span class="text-foreground font-mono">app_mention</span></template>
                        <template #im><span class="text-foreground font-mono">message.im</span></template>
                    </i18n-t>
                </SetupStep>
                <SetupStep :step="3">
                    <i18n-t keypath="agents.channels.setup.slack.steps.connected_3" tag="span">
                        <template #invite><span class="text-foreground font-mono">/invite</span></template>
                        <template #live>{{ t('agents.channels.setup.shared.reply_streams_live') }}</template>
                    </i18n-t>
                </SetupStep>
            </ol>
        </template>

        <template #setup>
            <ol class="text-muted-foreground space-y-2 text-xs">
                <SetupStep :step="1">
                    <i18n-t keypath="agents.channels.setup.slack.steps.connect_1" tag="span">
                        <template #link>
                            <a
                                href="https://api.slack.com/apps"
                                target="_blank"
                                rel="noopener"
                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                >api.slack.com/apps</a
                            >
                        </template>
                    </i18n-t>
                </SetupStep>
                <SetupStep :step="2">
                    <i18n-t keypath="agents.channels.setup.slack.steps.connect_2" tag="span">
                        <template #oauth>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.slack.oauth_permissions') }}</span>
                        </template>
                        <template #scope1><span class="text-foreground font-mono">app_mentions:read</span></template>
                        <template #scope2><span class="text-foreground font-mono">chat:write</span></template>
                        <template #scope3><span class="text-foreground font-mono">im:history</span></template>
                    </i18n-t>
                </SetupStep>
                <SetupStep :step="3">
                    <i18n-t keypath="agents.channels.setup.slack.steps.connect_3" tag="span">
                        <template #basic_info>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.slack.basic_information') }}</span>
                        </template>
                    </i18n-t>
                </SetupStep>
            </ol>
            <CredentialInput
                id="slack-bot-token"
                v-model="form.bot_token"
                :label="t('agents.channels.setup.labels.bot_token')"
                :icon="KeyRound"
                :placeholder="t('agents.channels.setup.placeholders.slack_bot_token')"
                :error="form.errors.bot_token"
            />
            <CredentialInput
                id="slack-signing-secret"
                v-model="form.signing_secret"
                :label="t('agents.channels.setup.labels.signing_secret')"
                :icon="Lock"
                secret
                :error="form.errors.signing_secret"
                @enter="connect"
            />
            <div class="border-t pt-5">
                <Button class="w-full" :disabled="form.processing || !form.bot_token || !form.signing_secret" @click="connect">
                    <LoaderCircle v-if="form.processing" class="me-2 size-4 animate-spin" />
                    <Plug v-else class="me-2 size-4" />
                    {{ t('agents.channels.connect') }}
                </Button>
            </div>
        </template>
    </ChannelPanel>
</template>
