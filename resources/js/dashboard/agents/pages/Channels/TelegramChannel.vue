<script setup lang="ts">
// Telegram slide-over: connect a bot with its BotFather token; once connected,
// show the bot and a summary of its slash commands (edited in TelegramCommandsDialog).
import { Button } from '@/shared/ui/button';
import { connect as connectTelegram } from '@/routes/agents/channels/telegram';
import { useForm } from '@inertiajs/vue3';
import { Command, KeyRound, LoaderCircle, Plug } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import ChannelPanel from './ChannelPanel.vue';
import ConnectedSummary from './ConnectedSummary.vue';
import CredentialInput from './CredentialInput.vue';
import SetupStep from './SetupStep.vue';
import type { ChannelAgent, TelegramChannelProps } from './types';

const props = defineProps<{
    agent: ChannelAgent;
    telegram: TelegramChannelProps;
    canManage: boolean;
}>();

const emit = defineEmits<{ disconnect: []; manageCommands: [] }>();

const { t } = useI18n();

const form = useForm({ bot_token: '' });

const connect = () => {
    form.post(connectTelegram.url(props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            toast.success(t('agents.channels.telegram.connected_toast'));
        },
    });
};
</script>

<template>
    <ChannelPanel
        :agent="agent"
        :connected="telegram.connected"
        :can-manage="canManage"
        :channel-name="t('agents.channels.setup.names.telegram')"
        @disconnect="emit('disconnect')"
    >
        <template #connected>
            <ConnectedSummary>@{{ telegram.bot_username }}</ConnectedSummary>
            <p class="text-muted-foreground text-sm">
                {{ t('agents.channels.setup.telegram.connected_description') }}
            </p>

            <!-- Custom commands -->
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium">{{ t('agents.channels.setup.telegram.commands.title') }}</p>
                        <p class="text-muted-foreground truncate text-xs">
                            {{
                                telegram.commands.length
                                    ? telegram.commands.map((c) => '/' + c.command).join(', ')
                                    : t('agents.channels.setup.telegram.commands.empty')
                            }}
                        </p>
                    </div>
                    <Button v-if="canManage" variant="outline" size="sm" class="shrink-0" @click="emit('manageCommands')">
                        <Command class="me-2 size-4" />
                        {{ t('agents.channels.setup.telegram.commands.manage') }}
                    </Button>
                </div>
                <p class="text-muted-foreground mt-3 text-xs">
                    {{ t('agents.channels.setup.telegram.commands.builtins_note') }}
                </p>
            </div>
        </template>

        <template #setup>
            <ol class="text-muted-foreground space-y-2 text-xs">
                <SetupStep :step="1">
                    <i18n-t keypath="agents.channels.setup.telegram.steps.connect_1" tag="span">
                        <template #botfather>
                            <span class="text-foreground font-medium">{{ t('agents.channels.setup.telegram.botfather') }}</span>
                        </template>
                    </i18n-t>
                </SetupStep>
                <SetupStep :step="2">
                    <i18n-t keypath="agents.channels.setup.telegram.steps.connect_2" tag="span">
                        <template #command><span class="text-foreground font-mono">/newbot</span></template>
                    </i18n-t>
                </SetupStep>
                <SetupStep :step="3">{{ t('agents.channels.setup.telegram.steps.connect_3') }}</SetupStep>
            </ol>
            <CredentialInput
                id="bot-token"
                v-model="form.bot_token"
                :label="t('agents.channels.setup.labels.bot_token')"
                :icon="KeyRound"
                :placeholder="t('agents.channels.setup.placeholders.telegram_bot_token')"
                :error="form.errors.bot_token"
                @enter="connect"
            />
            <div class="border-t pt-5">
                <Button class="w-full" :disabled="form.processing || !form.bot_token" @click="connect">
                    <LoaderCircle v-if="form.processing" class="me-2 size-4 animate-spin" />
                    <Plug v-else class="me-2 size-4" />
                    {{ t('agents.channels.connect') }}
                </Button>
            </div>
        </template>
    </ChannelPanel>
</template>
