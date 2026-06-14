<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, Check, Fingerprint, Hash, KeyRound, Link2, LoaderCircle, Lock, Plug, Settings2, ShieldCheck, Unplug } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import CopyField from './CopyField.vue';
import CredentialInput from './CredentialInput.vue';

const { t } = useI18n();

interface Props {
    agent: { external_id: string; name: string; is_public: boolean };
    telegram: { connected: boolean; bot_username: string | null; enabled: boolean };
    slack: { connected: boolean; team_name: string | null; webhook_url: string | null; enabled: boolean };
    messenger: { connected: boolean; page_name: string | null; webhook_url: string | null; verify_token: string | null; enabled: boolean };
    whatsapp: {
        connected: boolean;
        display_phone_number: string | null;
        verified_name: string | null;
        webhook_url: string | null;
        verify_token: string | null;
        enabled: boolean;
    };
    msteams: { connected: boolean; app_id: string | null; webhook_url: string | null; enabled: boolean };
    instagram: { connected: boolean; username: string | null; webhook_url: string | null; verify_token: string | null; enabled: boolean };
}

const props = defineProps<Props>();

const { hasPermission } = useTeamPermissions();
const canManage = computed(() => hasPermission('agent.setup'));

type ChannelKind = 'telegram' | 'slack' | 'whatsapp' | 'messenger' | 'msteams' | 'instagram';

interface ChannelItem {
    kind: ChannelKind;
    name: string;
    tagline: string;
    logo: string;
    badge?: { label: string; variant: 'default' | 'secondary' | 'outline' };
}

const items = computed<ChannelItem[]>(() => [
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
            : t('agents.channels.messenger.tagline'),
        logo: '/images/logos/channels/messenger.svg',
        badge: props.messenger.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
    },
    {
        kind: 'whatsapp',
        name: t('agents.channels.setup.names.whatsapp'),
        tagline: props.whatsapp.connected
            ? t('agents.channels.whatsapp.tagline_connected', { name: props.whatsapp.verified_name ?? props.whatsapp.display_phone_number })
            : t('agents.channels.whatsapp.tagline'),
        logo: '/images/logos/channels/whatsapp.svg',
        badge: props.whatsapp.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
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
            : t('agents.channels.instagram.tagline'),
        logo: '/images/logos/channels/instagram.svg',
        badge: props.instagram.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
    },
]);

// ── Config sidebar -----------------------------------------------------------
// `selectedKind` (not the object) so the open sheet always reflects fresh props
// after a connect/disconnect reload.
const sheetOpen = ref(false);
const selectedKind = ref<ChannelKind | null>(null);
const selected = computed(() => items.value.find((i) => i.kind === selectedKind.value) ?? null);

const openChannel = (item: ChannelItem) => {
    selectedKind.value = item.kind;
    telegramForm.reset();
    telegramForm.clearErrors();
    slackForm.reset();
    slackForm.clearErrors();
    messengerForm.reset();
    messengerForm.clearErrors();
    whatsappForm.reset();
    whatsappForm.clearErrors();
    msteamsForm.reset();
    msteamsForm.clearErrors();
    instagramForm.reset();
    instagramForm.clearErrors();
    sheetOpen.value = true;
};

// ── Telegram connect ----------------------------------------------------------
const telegramForm = useForm({ bot_token: '' });

const connectTelegram = () => {
    telegramForm.post(route('agents.channels.telegram.connect', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            telegramForm.reset();
            toast.success(t('agents.channels.telegram.connected_toast'));
        },
    });
};

// ── Slack connect -------------------------------------------------------------
const slackForm = useForm({ bot_token: '', signing_secret: '' });

const connectSlack = () => {
    slackForm.post(route('agents.channels.slack.connect', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            slackForm.reset();
            toast.success(t('agents.channels.slack.connected_toast'));
        },
    });
};

// ── Messenger connect ----------------------------------------------------------
const messengerForm = useForm({ page_access_token: '', app_secret: '' });

const connectMessenger = () => {
    messengerForm.post(route('agents.channels.messenger.connect', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            messengerForm.reset();
            toast.success(t('agents.channels.messenger.connected_toast'));
        },
    });
};

// ── WhatsApp connect ------------------------------------------------------------
const whatsappForm = useForm({ access_token: '', app_secret: '', phone_number_id: '', waba_id: '' });

const connectWhatsapp = () => {
    whatsappForm.post(route('agents.channels.whatsapp.connect', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            whatsappForm.reset();
            toast.success(t('agents.channels.whatsapp.connected_toast'));
        },
    });
};

// ── Microsoft Teams connect -----------------------------------------------------
const msteamsForm = useForm({ app_id: '', app_secret: '', tenant_id: '' });

const connectMsteams = () => {
    msteamsForm.post(route('agents.channels.msteams.connect', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            msteamsForm.reset();
            toast.success(t('agents.channels.msteams.connected_toast'));
        },
    });
};

// ── Instagram connect -----------------------------------------------------------
const instagramForm = useForm({ access_token: '', app_secret: '' });

const connectInstagram = () => {
    instagramForm.post(route('agents.channels.instagram.connect', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            instagramForm.reset();
            toast.success(t('agents.channels.instagram.connected_toast'));
        },
    });
};

// ── Disconnect (shared dialog) ------------------------------------------------
const disconnectOpen = ref(false);

const disconnect = () => {
    const kind = selectedKind.value;
    if (kind === null) return;

    const name = items.value.find((i) => i.kind === kind)?.name ?? kind;

    router.delete(route(`agents.channels.${kind}.disconnect`, props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            disconnectOpen.value = false;
            toast.success(t('agents.channels.disconnected_toast', { name }));
        },
    });
};
</script>

<template>
    <AgentLayout :agent="props.agent" :title="t('agents.channels.title')" :description="t('agents.channels.description')">
        <Head :title="t('agents.channels.head_title', { name: props.agent.name })" />

        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-4xl">
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                    <div
                        v-for="item in items"
                        :key="item.kind"
                        class="bg-card hover:border-foreground/20 relative flex flex-col rounded-xl border p-5 transition hover:shadow-sm sm:aspect-square"
                    >
                        <Badge v-if="item.badge" :variant="item.badge.variant" class="absolute end-3 top-3">{{ item.badge.label }}</Badge>
                        <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                            <img :src="item.logo" :alt="item.name" class="size-14" />
                            <p class="font-medium">{{ item.name }}</p>
                            <p class="text-muted-foreground line-clamp-2 text-sm">{{ item.tagline }}</p>
                        </div>
                        <Button variant="outline" class="mt-4 w-full" @click="openChannel(item)">
                            <Settings2 class="me-2 size-4" />
                            {{ t('agents.channels.setup_tab') }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Config sidebar -->
        <Sheet v-model:open="sheetOpen">
            <SheetContent class="w-full overflow-y-auto p-0 sm:max-w-md">
                <template v-if="selected">
                    <SheetHeader class="border-b p-6">
                        <div class="mb-1 flex items-center gap-3">
                            <img :src="selected.logo" :alt="selected.name" class="size-9" />
                            <SheetTitle>{{ selected.name }}</SheetTitle>
                        </div>
                        <SheetDescription>{{ selected.tagline }}</SheetDescription>
                    </SheetHeader>

                    <div class="space-y-5 p-6">
                        <!-- Telegram -->
                        <template v-if="selected.kind === 'telegram'">
                            <!-- Connected -->
                            <template v-if="props.telegram.connected">
                                <div class="bg-muted/50 flex items-center gap-3 rounded-lg border p-4">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
                                        <Check class="size-5 text-emerald-600" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">{{ t('agents.channels.connected') }}</p>
                                        <p class="text-muted-foreground truncate text-sm">@{{ props.telegram.bot_username }}</p>
                                    </div>
                                </div>
                                <p class="text-muted-foreground text-sm">
                                    {{ t('agents.channels.setup.telegram.connected_description') }}
                                </p>
                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: t('agents.channels.setup.names.telegram') }) }}
                                </div>
                                <div v-if="canManage" class="border-t pt-5">
                                    <Button variant="outline" class="text-destructive hover:text-destructive w-full" @click="disconnectOpen = true">
                                        <Unplug class="me-2 size-4" />
                                        {{ t('agents.channels.disconnect') }}
                                    </Button>
                                </div>
                            </template>

                            <!-- Protected agent: messaging channels are public-only -->
                            <template v-else-if="!props.agent.is_public">
                                <div class="bg-muted/40 rounded-lg border border-dashed p-5 text-center">
                                    <p class="text-sm font-medium">{{ t('agents.channels.private_title') }}</p>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        <i18n-t keypath="agents.channels.setup.public_only" tag="span" scope="global">
                                            <template #channel>{{ t('agents.channels.setup.names.telegram') }}</template>
                                            <template #security>
                                                <Link
                                                    :href="route('agents.identity', props.agent.external_id)"
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
                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.telegram.steps.connect_1" tag="span">
                                                <template #botfather>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.telegram.botfather')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.telegram.steps.connect_2" tag="span">
                                                <template #command><span class="text-foreground font-mono">/newbot</span></template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>{{ t('agents.channels.setup.telegram.steps.connect_3') }}</span>
                                    </li>
                                </ol>
                                <CredentialInput
                                    id="bot-token"
                                    v-model="telegramForm.bot_token"
                                    :label="t('agents.channels.setup.labels.bot_token')"
                                    :icon="KeyRound"
                                    :placeholder="t('agents.channels.setup.placeholders.telegram_bot_token')"
                                    :error="telegramForm.errors.bot_token"
                                    @enter="connectTelegram"
                                />
                                <div class="border-t pt-5">
                                    <Button class="w-full" :disabled="telegramForm.processing || !telegramForm.bot_token" @click="connectTelegram">
                                        <LoaderCircle v-if="telegramForm.processing" class="me-2 size-4 animate-spin" />
                                        <Plug v-else class="me-2 size-4" />
                                        {{ t('agents.channels.connect') }}
                                    </Button>
                                </div>
                            </template>

                            <template v-else>
                                <p class="text-muted-foreground text-sm">{{ t('agents.channels.no_permission') }}</p>
                            </template>
                        </template>

                        <!-- Slack -->
                        <template v-else-if="selected.kind === 'slack'">
                            <!-- Connected -->
                            <template v-if="props.slack.connected">
                                <div class="bg-muted/50 flex items-center gap-3 rounded-lg border p-4">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
                                        <Check class="size-5 text-emerald-600" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">{{ t('agents.channels.connected') }}</p>
                                        <p class="text-muted-foreground truncate text-sm">{{ props.slack.team_name }}</p>
                                    </div>
                                </div>

                                <CopyField
                                    id="slack-webhook-url"
                                    :label="t('agents.channels.setup.labels.request_url')"
                                    :icon="Link2"
                                    :value="props.slack.webhook_url"
                                />

                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
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
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.slack.event_subscriptions')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.slack.steps.connected_2" tag="span">
                                                <template #mention><span class="text-foreground font-mono">app_mention</span></template>
                                                <template #im><span class="text-foreground font-mono">message.im</span></template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.slack.steps.connected_3" tag="span">
                                                <template #invite><span class="text-foreground font-mono">/invite</span></template>
                                                <template #live>{{ t('agents.channels.setup.shared.reply_streams_live') }}</template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                </ol>

                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: t('agents.channels.setup.names.slack') }) }}
                                </div>
                                <div v-if="canManage" class="border-t pt-5">
                                    <Button variant="outline" class="text-destructive hover:text-destructive w-full" @click="disconnectOpen = true">
                                        <Unplug class="me-2 size-4" />
                                        {{ t('agents.channels.disconnect') }}
                                    </Button>
                                </div>
                            </template>

                            <!-- Protected agent: messaging channels are public-only -->
                            <template v-else-if="!props.agent.is_public">
                                <div class="bg-muted/40 rounded-lg border border-dashed p-5 text-center">
                                    <p class="text-sm font-medium">{{ t('agents.channels.private_title') }}</p>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        <i18n-t keypath="agents.channels.setup.public_only" tag="span" scope="global">
                                            <template #channel>{{ t('agents.channels.setup.names.slack') }}</template>
                                            <template #security>
                                                <Link
                                                    :href="route('agents.identity', props.agent.external_id)"
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
                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
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
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.slack.steps.connect_2" tag="span">
                                                <template #oauth>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.slack.oauth_permissions')
                                                    }}</span>
                                                </template>
                                                <template #scope1><span class="text-foreground font-mono">app_mentions:read</span></template>
                                                <template #scope2><span class="text-foreground font-mono">chat:write</span></template>
                                                <template #scope3><span class="text-foreground font-mono">im:history</span></template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.slack.steps.connect_3" tag="span">
                                                <template #basic_info>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.slack.basic_information')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                </ol>
                                <CredentialInput
                                    id="slack-bot-token"
                                    v-model="slackForm.bot_token"
                                    :label="t('agents.channels.setup.labels.bot_token')"
                                    :icon="KeyRound"
                                    :placeholder="t('agents.channels.setup.placeholders.slack_bot_token')"
                                    :error="slackForm.errors.bot_token"
                                />
                                <CredentialInput
                                    id="slack-signing-secret"
                                    v-model="slackForm.signing_secret"
                                    :label="t('agents.channels.setup.labels.signing_secret')"
                                    :icon="Lock"
                                    secret
                                    :error="slackForm.errors.signing_secret"
                                    @enter="connectSlack"
                                />
                                <div class="border-t pt-5">
                                    <Button
                                        class="w-full"
                                        :disabled="slackForm.processing || !slackForm.bot_token || !slackForm.signing_secret"
                                        @click="connectSlack"
                                    >
                                        <LoaderCircle v-if="slackForm.processing" class="me-2 size-4 animate-spin" />
                                        <Plug v-else class="me-2 size-4" />
                                        {{ t('agents.channels.connect') }}
                                    </Button>
                                </div>
                            </template>

                            <template v-else>
                                <p class="text-muted-foreground text-sm">{{ t('agents.channels.no_permission') }}</p>
                            </template>
                        </template>

                        <!-- Messenger -->
                        <template v-else-if="selected.kind === 'messenger'">
                            <!-- Connected -->
                            <template v-if="props.messenger.connected">
                                <div class="bg-muted/50 flex items-center gap-3 rounded-lg border p-4">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
                                        <Check class="size-5 text-emerald-600" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">{{ t('agents.channels.connected') }}</p>
                                        <p class="text-muted-foreground truncate text-sm">{{ props.messenger.page_name }}</p>
                                    </div>
                                </div>

                                <CopyField
                                    id="messenger-webhook-url"
                                    :label="t('agents.channels.setup.labels.callback_url')"
                                    :icon="Link2"
                                    :value="props.messenger.webhook_url"
                                />
                                <CopyField
                                    id="messenger-verify-token"
                                    :label="t('agents.channels.setup.labels.verify_token')"
                                    :icon="ShieldCheck"
                                    :value="props.messenger.verify_token"
                                />

                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.messenger.steps.connected_1" tag="span">
                                                <template #link>
                                                    <a
                                                        href="https://developers.facebook.com/apps"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                        >{{ t('agents.channels.setup.messenger.your_meta_app') }}</a
                                                    >
                                                </template>
                                                <template #webhooks>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.messenger.webhooks_path')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.shared.subscribe_webhook" tag="span">
                                                <template #field>
                                                    <span class="text-foreground font-mono">{{
                                                        t('agents.channels.setup.shared.messages_field')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>{{ t('agents.channels.setup.messenger.steps.connected_3') }}</span>
                                    </li>
                                </ol>

                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: t('agents.channels.setup.names.messenger') }) }}
                                </div>
                                <div v-if="canManage" class="border-t pt-5">
                                    <Button variant="outline" class="text-destructive hover:text-destructive w-full" @click="disconnectOpen = true">
                                        <Unplug class="me-2 size-4" />
                                        {{ t('agents.channels.disconnect') }}
                                    </Button>
                                </div>
                            </template>

                            <!-- Protected agent: messaging channels are public-only -->
                            <template v-else-if="!props.agent.is_public">
                                <div class="bg-muted/40 rounded-lg border border-dashed p-5 text-center">
                                    <p class="text-sm font-medium">{{ t('agents.channels.private_title') }}</p>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        <i18n-t keypath="agents.channels.setup.public_only" tag="span" scope="global">
                                            <template #channel>{{ t('agents.channels.setup.names.messenger') }}</template>
                                            <template #security>
                                                <Link
                                                    :href="route('agents.identity', props.agent.external_id)"
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
                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.messenger.steps.connect_1" tag="span">
                                                <template #link>
                                                    <a
                                                        href="https://developers.facebook.com/apps"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                        >developers.facebook.com</a
                                                    >
                                                </template>
                                                <template #product>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.messenger.product')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.messenger.steps.connect_perms" tag="span">
                                                <template #permissions>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.messenger.permissions_path')
                                                    }}</span>
                                                </template>
                                                <template #perm1><span class="text-foreground font-mono">pages_messaging</span></template>
                                                <template #perm2><span class="text-foreground font-mono">pages_read_engagement</span></template>
                                                <template #perm3><span class="text-foreground font-mono">pages_manage_metadata</span></template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.messenger.steps.connect_2" tag="span">
                                                <template #api_settings>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.messenger.api_settings_path')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >4</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.shared.copy_app_secret_both" tag="span">
                                                <template #section>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.messenger.app_settings_basic')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                </ol>
                                <CredentialInput
                                    id="messenger-page-token"
                                    v-model="messengerForm.page_access_token"
                                    :label="t('agents.channels.setup.labels.page_access_token')"
                                    :icon="KeyRound"
                                    :placeholder="t('agents.channels.setup.placeholders.meta_token')"
                                    :error="messengerForm.errors.page_access_token"
                                />
                                <CredentialInput
                                    id="messenger-app-secret"
                                    v-model="messengerForm.app_secret"
                                    :label="t('agents.channels.setup.labels.app_secret')"
                                    :icon="Lock"
                                    secret
                                    :error="messengerForm.errors.app_secret"
                                    @enter="connectMessenger"
                                />
                                <div class="border-t pt-5">
                                    <Button
                                        class="w-full"
                                        :disabled="messengerForm.processing || !messengerForm.page_access_token || !messengerForm.app_secret"
                                        @click="connectMessenger"
                                    >
                                        <LoaderCircle v-if="messengerForm.processing" class="me-2 size-4 animate-spin" />
                                        <Plug v-else class="me-2 size-4" />
                                        {{ t('agents.channels.connect') }}
                                    </Button>
                                </div>
                            </template>

                            <template v-else>
                                <p class="text-muted-foreground text-sm">{{ t('agents.channels.no_permission') }}</p>
                            </template>
                        </template>

                        <!-- WhatsApp -->
                        <template v-else-if="selected.kind === 'whatsapp'">
                            <!-- Connected -->
                            <template v-if="props.whatsapp.connected">
                                <div class="bg-muted/50 flex items-center gap-3 rounded-lg border p-4">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
                                        <Check class="size-5 text-emerald-600" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">{{ t('agents.channels.connected') }}</p>
                                        <p class="text-muted-foreground truncate text-sm">
                                            {{ props.whatsapp.verified_name ?? props.whatsapp.display_phone_number }}
                                            <template v-if="props.whatsapp.verified_name && props.whatsapp.display_phone_number">
                                                · {{ props.whatsapp.display_phone_number }}
                                            </template>
                                        </p>
                                    </div>
                                </div>

                                <CopyField
                                    id="whatsapp-webhook-url"
                                    :label="t('agents.channels.setup.labels.callback_url')"
                                    :icon="Link2"
                                    :value="props.whatsapp.webhook_url"
                                />
                                <CopyField
                                    id="whatsapp-verify-token"
                                    :label="t('agents.channels.setup.labels.verify_token')"
                                    :icon="ShieldCheck"
                                    :value="props.whatsapp.verify_token"
                                />

                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.whatsapp.steps.connected_1" tag="span">
                                                <template #link>
                                                    <a
                                                        href="https://developers.facebook.com/apps"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                        >{{ t('agents.channels.setup.messenger.your_meta_app') }}</a
                                                    >
                                                </template>
                                                <template #configuration>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.whatsapp.configuration_path')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.shared.subscribe_webhook" tag="span">
                                                <template #field>
                                                    <span class="text-foreground font-mono">{{
                                                        t('agents.channels.setup.shared.messages_field')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>{{ t('agents.channels.setup.whatsapp.steps.connected_3') }}</span>
                                    </li>
                                </ol>
                                <p class="text-muted-foreground text-xs">
                                    {{ t('agents.channels.setup.guide.need_detail_before') }}
                                    <a
                                        href="https://docs.chatvia.ai/guide/channels/whatsapp"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                        >{{ t('agents.channels.setup.guide.step_by_step_guide') }}</a
                                    >
                                    {{ t('agents.channels.setup.guide.need_detail_after') }}
                                </p>

                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: t('agents.channels.setup.names.whatsapp') }) }}
                                </div>
                                <div v-if="canManage" class="border-t pt-5">
                                    <Button variant="outline" class="text-destructive hover:text-destructive w-full" @click="disconnectOpen = true">
                                        <Unplug class="me-2 size-4" />
                                        {{ t('agents.channels.disconnect') }}
                                    </Button>
                                </div>
                            </template>

                            <!-- Protected agent: messaging channels are public-only -->
                            <template v-else-if="!props.agent.is_public">
                                <div class="bg-muted/40 rounded-lg border border-dashed p-5 text-center">
                                    <p class="text-sm font-medium">{{ t('agents.channels.private_title') }}</p>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        <i18n-t keypath="agents.channels.setup.public_only" tag="span" scope="global">
                                            <template #channel>{{ t('agents.channels.setup.names.whatsapp') }}</template>
                                            <template #security>
                                                <Link
                                                    :href="route('agents.identity', props.agent.external_id)"
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
                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.whatsapp.steps.connect_1" tag="span">
                                                <template #link>
                                                    <a
                                                        href="https://developers.facebook.com/apps"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                        >developers.facebook.com</a
                                                    >
                                                </template>
                                                <template #use_case>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.whatsapp.use_case')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.whatsapp.steps.connect_2" tag="span">
                                                <template #basic_setup>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.whatsapp.basic_setup_path')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.shared.copy_app_secret_all" tag="span">
                                                <template #section>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.messenger.app_settings_basic')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                </ol>
                                <p class="text-muted-foreground text-xs">
                                    {{ t('agents.channels.setup.guide.need_detail_before') }}
                                    <a
                                        href="https://docs.chatvia.ai/guide/channels/whatsapp"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                        >{{ t('agents.channels.setup.guide.step_by_step_guide') }}</a
                                    >
                                    {{ t('agents.channels.setup.guide.need_detail_after') }}
                                </p>
                                <CredentialInput
                                    id="whatsapp-phone-number-id"
                                    v-model="whatsappForm.phone_number_id"
                                    :label="t('agents.channels.setup.labels.phone_number_id')"
                                    :icon="Hash"
                                    :placeholder="t('agents.channels.setup.placeholders.phone_number_id')"
                                    :error="whatsappForm.errors.phone_number_id"
                                />
                                <CredentialInput
                                    id="whatsapp-waba-id"
                                    v-model="whatsappForm.waba_id"
                                    :label="t('agents.channels.setup.labels.waba_id')"
                                    :icon="Building2"
                                    :placeholder="t('agents.channels.setup.placeholders.phone_number_id')"
                                    :error="whatsappForm.errors.waba_id"
                                />
                                <CredentialInput
                                    id="whatsapp-access-token"
                                    v-model="whatsappForm.access_token"
                                    :label="t('agents.channels.setup.labels.access_token')"
                                    :icon="KeyRound"
                                    :placeholder="t('agents.channels.setup.placeholders.meta_token')"
                                    :error="whatsappForm.errors.access_token"
                                />
                                <CredentialInput
                                    id="whatsapp-app-secret"
                                    v-model="whatsappForm.app_secret"
                                    :label="t('agents.channels.setup.labels.app_secret')"
                                    :icon="Lock"
                                    secret
                                    :error="whatsappForm.errors.app_secret"
                                    @enter="connectWhatsapp"
                                />
                                <div class="border-t pt-5">
                                    <Button
                                        class="w-full"
                                        :disabled="
                                            whatsappForm.processing ||
                                            !whatsappForm.access_token ||
                                            !whatsappForm.app_secret ||
                                            !whatsappForm.phone_number_id ||
                                            !whatsappForm.waba_id
                                        "
                                        @click="connectWhatsapp"
                                    >
                                        <LoaderCircle v-if="whatsappForm.processing" class="me-2 size-4 animate-spin" />
                                        <Plug v-else class="me-2 size-4" />
                                        {{ t('agents.channels.connect') }}
                                    </Button>
                                </div>
                            </template>

                            <template v-else>
                                <p class="text-muted-foreground text-sm">{{ t('agents.channels.no_permission') }}</p>
                            </template>
                        </template>

                        <!-- Microsoft Teams -->
                        <template v-else-if="selected.kind === 'msteams'">
                            <!-- Connected -->
                            <template v-if="props.msteams.connected">
                                <div class="bg-muted/50 flex items-center gap-3 rounded-lg border p-4">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
                                        <Check class="size-5 text-emerald-600" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">{{ t('agents.channels.connected') }}</p>
                                        <p class="text-muted-foreground truncate text-sm">
                                            {{ t('agents.channels.setup.msteams.app_id_label', { id: props.msteams.app_id }) }}
                                        </p>
                                    </div>
                                </div>

                                <CopyField
                                    id="msteams-webhook-url"
                                    :label="t('agents.channels.setup.labels.messaging_endpoint')"
                                    :icon="Link2"
                                    :value="props.msteams.webhook_url"
                                />

                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
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
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.msteams.configuration')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.msteams.steps.connected_2" tag="span">
                                                <template #channels>
                                                    <span class="text-foreground font-medium">{{ t('agents.channels.setup.msteams.channels') }}</span>
                                                </template>
                                                <template #teams>
                                                    <span class="text-foreground font-medium">{{ t('agents.channels.setup.names.msteams') }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.msteams.steps.connected_3" tag="span">
                                                <template #open_chat>
                                                    <a
                                                        :href="`https://teams.microsoft.com/l/chat/0/0?users=28:${props.msteams.app_id}`"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                        >{{ t('agents.channels.setup.msteams.open_chat_in_teams') }}</a
                                                    >
                                                </template>
                                                <template #live>{{ t('agents.channels.setup.shared.reply_streams_live') }}</template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                </ol>

                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: t('agents.channels.setup.names.msteams') }) }}
                                </div>
                                <div v-if="canManage" class="border-t pt-5">
                                    <Button variant="outline" class="text-destructive hover:text-destructive w-full" @click="disconnectOpen = true">
                                        <Unplug class="me-2 size-4" />
                                        {{ t('agents.channels.disconnect') }}
                                    </Button>
                                </div>
                            </template>

                            <!-- Protected agent: messaging channels are public-only -->
                            <template v-else-if="!props.agent.is_public">
                                <div class="bg-muted/40 rounded-lg border border-dashed p-5 text-center">
                                    <p class="text-sm font-medium">{{ t('agents.channels.private_title') }}</p>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        <i18n-t keypath="agents.channels.setup.public_only" tag="span" scope="global">
                                            <template #channel>{{ t('agents.channels.setup.names.msteams') }}</template>
                                            <template #security>
                                                <Link
                                                    :href="route('agents.identity', props.agent.external_id)"
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
                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.msteams.steps.connect_1" tag="span">
                                                <template #azure_bot>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.msteams.azure_bot')
                                                    }}</span>
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
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.msteams.steps.connect_2" tag="span">
                                                <template #app_id>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.msteams.microsoft_app_id')
                                                    }}</span>
                                                </template>
                                                <template #secrets>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.msteams.manage_password')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.msteams.steps.connect_3" tag="span">
                                                <template #tenant_id>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.msteams.directory_tenant_id')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                </ol>
                                <CredentialInput
                                    id="msteams-app-id"
                                    v-model="msteamsForm.app_id"
                                    :label="t('agents.channels.setup.labels.microsoft_app_id')"
                                    :icon="Fingerprint"
                                    :placeholder="t('agents.channels.setup.placeholders.microsoft_app_id')"
                                    :error="msteamsForm.errors.app_id"
                                />
                                <CredentialInput
                                    id="msteams-app-secret"
                                    v-model="msteamsForm.app_secret"
                                    :label="t('agents.channels.setup.labels.client_secret')"
                                    :icon="Lock"
                                    secret
                                    :error="msteamsForm.errors.app_secret"
                                />
                                <CredentialInput
                                    id="msteams-tenant-id"
                                    v-model="msteamsForm.tenant_id"
                                    :label="t('agents.channels.setup.labels.tenant_id')"
                                    :hint="t('agents.channels.setup.hints.tenant_single_only')"
                                    :icon="Building2"
                                    :placeholder="t('agents.channels.setup.placeholders.tenant_id')"
                                    :error="msteamsForm.errors.tenant_id"
                                    @enter="connectMsteams"
                                />
                                <div class="border-t pt-5">
                                    <Button
                                        class="w-full"
                                        :disabled="msteamsForm.processing || !msteamsForm.app_id || !msteamsForm.app_secret"
                                        @click="connectMsteams"
                                    >
                                        <LoaderCircle v-if="msteamsForm.processing" class="me-2 size-4 animate-spin" />
                                        <Plug v-else class="me-2 size-4" />
                                        {{ t('agents.channels.connect') }}
                                    </Button>
                                </div>
                            </template>

                            <template v-else>
                                <p class="text-muted-foreground text-sm">{{ t('agents.channels.no_permission') }}</p>
                            </template>
                        </template>

                        <!-- Instagram -->
                        <template v-else-if="selected.kind === 'instagram'">
                            <!-- Connected -->
                            <template v-if="props.instagram.connected">
                                <div class="bg-muted/50 flex items-center gap-3 rounded-lg border p-4">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
                                        <Check class="size-5 text-emerald-600" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">{{ t('agents.channels.connected') }}</p>
                                        <p class="text-muted-foreground truncate text-sm">@{{ props.instagram.username }}</p>
                                    </div>
                                </div>

                                <CopyField
                                    id="instagram-webhook-url"
                                    :label="t('agents.channels.setup.labels.callback_url')"
                                    :icon="Link2"
                                    :value="props.instagram.webhook_url"
                                />
                                <CopyField
                                    id="instagram-verify-token"
                                    :label="t('agents.channels.setup.labels.verify_token')"
                                    :icon="ShieldCheck"
                                    :value="props.instagram.verify_token"
                                />

                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.instagram.steps.connected_1" tag="span">
                                                <template #link>
                                                    <a
                                                        href="https://developers.facebook.com/apps"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                        >{{ t('agents.channels.setup.messenger.your_meta_app') }}</a
                                                    >
                                                </template>
                                                <template #webhooks>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.instagram.webhooks_path')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.shared.subscribe_webhook" tag="span">
                                                <template #field>
                                                    <span class="text-foreground font-mono">{{
                                                        t('agents.channels.setup.shared.messages_field')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.instagram.steps.connected_3" tag="span">
                                                <template #message_controls>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.instagram.message_controls_path')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                </ol>

                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: t('agents.channels.setup.names.instagram') }) }}
                                </div>
                                <div v-if="canManage" class="border-t pt-5">
                                    <Button variant="outline" class="text-destructive hover:text-destructive w-full" @click="disconnectOpen = true">
                                        <Unplug class="me-2 size-4" />
                                        {{ t('agents.channels.disconnect') }}
                                    </Button>
                                </div>
                            </template>

                            <!-- Protected agent: messaging channels are public-only -->
                            <template v-else-if="!props.agent.is_public">
                                <div class="bg-muted/40 rounded-lg border border-dashed p-5 text-center">
                                    <p class="text-sm font-medium">{{ t('agents.channels.private_title') }}</p>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        <i18n-t keypath="agents.channels.setup.public_only" tag="span" scope="global">
                                            <template #channel>{{ t('agents.channels.setup.names.instagram') }}</template>
                                            <template #security>
                                                <Link
                                                    :href="route('agents.identity', props.agent.external_id)"
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
                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.instagram.steps.connect_1" tag="span">
                                                <template #link>
                                                    <a
                                                        href="https://developers.facebook.com/apps"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                        >developers.facebook.com</a
                                                    >
                                                </template>
                                                <template #product>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.instagram.product')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>
                                            <i18n-t keypath="agents.channels.setup.instagram.steps.connect_2" tag="span">
                                                <template #api_setup>
                                                    <span class="text-foreground font-medium">{{
                                                        t('agents.channels.setup.instagram.api_setup_path')
                                                    }}</span>
                                                </template>
                                            </i18n-t>
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>{{ t('agents.channels.setup.shared.copy_app_secret_same_page') }}</span>
                                    </li>
                                </ol>
                                <CredentialInput
                                    id="instagram-access-token"
                                    v-model="instagramForm.access_token"
                                    :label="t('agents.channels.setup.labels.access_token')"
                                    :icon="KeyRound"
                                    :placeholder="t('agents.channels.setup.placeholders.instagram_token')"
                                    :error="instagramForm.errors.access_token"
                                />
                                <CredentialInput
                                    id="instagram-app-secret"
                                    v-model="instagramForm.app_secret"
                                    :label="t('agents.channels.setup.labels.app_secret')"
                                    :icon="Lock"
                                    secret
                                    :error="instagramForm.errors.app_secret"
                                    @enter="connectInstagram"
                                />
                                <div class="border-t pt-5">
                                    <Button
                                        class="w-full"
                                        :disabled="instagramForm.processing || !instagramForm.access_token || !instagramForm.app_secret"
                                        @click="connectInstagram"
                                    >
                                        <LoaderCircle v-if="instagramForm.processing" class="me-2 size-4 animate-spin" />
                                        <Plug v-else class="me-2 size-4" />
                                        {{ t('agents.channels.connect') }}
                                    </Button>
                                </div>
                            </template>

                            <template v-else>
                                <p class="text-muted-foreground text-sm">{{ t('agents.channels.no_permission') }}</p>
                            </template>
                        </template>
                    </div>
                </template>
            </SheetContent>
        </Sheet>

        <!-- Disconnect confirmation -->
        <AlertDialog v-model:open="disconnectOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('agents.channels.disconnect_dialog.title', { name: selected?.name }) }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('agents.channels.disconnect_dialog.description', { name: selected?.name }) }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('agents.channels.disconnect_dialog.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="disconnect">
                        {{ t('agents.channels.disconnect') }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AgentLayout>
</template>
