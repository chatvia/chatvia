<script setup lang="ts">
// Microsoft Teams slide-over: connect an Azure Bot with its app id, client
// secret and (single-tenant) tenant id; once connected, show the messaging
// endpoint to paste into the bot's configuration and an "Open in Teams" link.
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import { Building2, Fingerprint, Link2, LoaderCircle, Lock, Plug } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import ChannelPanel from './ChannelPanel.vue';
import ConnectedSummary from './ConnectedSummary.vue';
import CopyField from './CopyField.vue';
import CredentialInput from './CredentialInput.vue';
import SetupStep from './SetupStep.vue';
import type { ChannelAgent, MsTeamsChannelProps } from './types';

const props = defineProps<{
    agent: ChannelAgent;
    msteams: MsTeamsChannelProps;
    canManage: boolean;
}>();

const emit = defineEmits<{ disconnect: [] }>();

const { t } = useI18n();

const form = useForm({ app_id: '', app_secret: '', tenant_id: '' });

const connect = () => {
    form.post(route('agents.channels.msteams.connect', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            toast.success(t('agents.channels.msteams.connected_toast'));
        },
    });
};
</script>

<template>
    <ChannelPanel
        :agent="agent"
        :connected="msteams.connected"
        :can-manage="canManage"
        :channel-name="t('agents.channels.setup.names.msteams')"
        @disconnect="emit('disconnect')"
    >
        <template #connected>
            <ConnectedSummary>
                {{ t('agents.channels.setup.msteams.app_id_label', { id: msteams.app_id }) }}
            </ConnectedSummary>

            <CopyField
                id="msteams-webhook-url"
                :label="t('agents.channels.setup.labels.messaging_endpoint')"
                :icon="Link2"
                :value="msteams.webhook_url"
            />

            <ol class="text-muted-foreground space-y-2 text-xs">
                <SetupStep :step="1">
                    <i18n-t keypath="agents.channels.setup.msteams.steps.connected_1" tag="span">
                        <template #link>
                            <a
                                href="https://portal.azure.com"
                                target="_blank"
                                rel="noopener"
                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                >{{ t('agents.channels.setup.msteams.azure_portal') }}</a
                            >
                        </template>
                        <template #configuration>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.msteams.configuration') }}</span>
                        </template>
                    </i18n-t>
                </SetupStep>
                <SetupStep :step="2">
                    <i18n-t keypath="agents.channels.setup.msteams.steps.connected_2" tag="span">
                        <template #channels>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.msteams.channels') }}</span>
                        </template>
                        <template #teams>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.names.msteams') }}</span>
                        </template>
                    </i18n-t>
                </SetupStep>
                <SetupStep :step="3">
                    <i18n-t keypath="agents.channels.setup.msteams.steps.connected_3" tag="span">
                        <template #open_chat>
                            <a
                                :href="`https://teams.microsoft.com/l/chat/0/0?users=28:${msteams.app_id}`"
                                target="_blank"
                                rel="noopener"
                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                >{{ t('agents.channels.setup.msteams.open_chat_in_teams') }}</a
                            >
                        </template>
                        <template #live>{{ t('agents.channels.setup.shared.reply_streams_live') }}</template>
                    </i18n-t>
                </SetupStep>
            </ol>
        </template>

        <template #setup>
            <ol class="text-muted-foreground space-y-2 text-xs">
                <SetupStep :step="1">
                    <i18n-t keypath="agents.channels.setup.msteams.steps.connect_1" tag="span">
                        <template #azure_bot>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.msteams.azure_bot') }}</span>
                        </template>
                        <template #link>
                            <a
                                href="https://portal.azure.com"
                                target="_blank"
                                rel="noopener"
                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                >portal.azure.com</a
                            >
                        </template>
                    </i18n-t>
                </SetupStep>
                <SetupStep :step="2">
                    <i18n-t keypath="agents.channels.setup.msteams.steps.connect_2" tag="span">
                        <template #app_id>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.msteams.microsoft_app_id') }}</span>
                        </template>
                        <template #secrets>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.msteams.manage_password') }}</span>
                        </template>
                    </i18n-t>
                </SetupStep>
                <SetupStep :step="3">
                    <i18n-t keypath="agents.channels.setup.msteams.steps.connect_3" tag="span">
                        <template #tenant_id>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.msteams.directory_tenant_id') }}</span>
                        </template>
                    </i18n-t>
                </SetupStep>
            </ol>
            <CredentialInput
                id="msteams-app-id"
                v-model="form.app_id"
                :label="t('agents.channels.setup.labels.microsoft_app_id')"
                :icon="Fingerprint"
                :placeholder="t('agents.channels.setup.placeholders.microsoft_app_id')"
                :error="form.errors.app_id"
            />
            <CredentialInput
                id="msteams-app-secret"
                v-model="form.app_secret"
                :label="t('agents.channels.setup.labels.client_secret')"
                :icon="Lock"
                secret
                :error="form.errors.app_secret"
            />
            <CredentialInput
                id="msteams-tenant-id"
                v-model="form.tenant_id"
                :label="t('agents.channels.setup.labels.tenant_id')"
                :hint="t('agents.channels.setup.hints.tenant_single_only')"
                :icon="Building2"
                :placeholder="t('agents.channels.setup.placeholders.tenant_id')"
                :error="form.errors.tenant_id"
                @enter="connect"
            />
            <div class="border-t pt-5">
                <Button class="w-full" :disabled="form.processing || !form.app_id || !form.app_secret" @click="connect">
                    <LoaderCircle v-if="form.processing" class="me-2 size-4 animate-spin" />
                    <Plug v-else class="me-2 size-4" />
                    {{ t('agents.channels.connect') }}
                </Button>
            </div>
        </template>
    </ChannelPanel>
</template>
