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
        name: 'Telegram',
        tagline: props.telegram.connected
            ? t('agents.channels.telegram.tagline_connected', { username: props.telegram.bot_username })
            : t('agents.channels.telegram.tagline'),
        logo: '/images/logos/channels/telegram.svg',
        badge: props.telegram.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
    },
    {
        kind: 'slack',
        name: 'Slack',
        tagline: props.slack.connected
            ? t('agents.channels.slack.tagline_connected', { team: props.slack.team_name })
            : t('agents.channels.slack.tagline'),
        logo: '/images/logos/channels/slack.svg',
        badge: props.slack.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
    },
    {
        kind: 'messenger',
        name: 'Messenger',
        tagline: props.messenger.connected
            ? t('agents.channels.messenger.tagline_connected', { page: props.messenger.page_name })
            : t('agents.channels.messenger.tagline'),
        logo: '/images/logos/channels/messenger.svg',
        badge: props.messenger.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
    },
    {
        kind: 'whatsapp',
        name: 'WhatsApp',
        tagline: props.whatsapp.connected
            ? t('agents.channels.whatsapp.tagline_connected', { name: props.whatsapp.verified_name ?? props.whatsapp.display_phone_number })
            : t('agents.channels.whatsapp.tagline'),
        logo: '/images/logos/channels/whatsapp.svg',
        badge: props.whatsapp.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
    },
    {
        kind: 'msteams',
        name: 'Microsoft Teams',
        tagline: props.msteams.connected ? t('agents.channels.msteams.tagline_connected') : t('agents.channels.msteams.tagline'),
        logo: '/images/logos/channels/msteams.svg',
        badge: props.msteams.connected ? { label: t('agents.channels.connected'), variant: 'default' } : undefined,
    },
    {
        kind: 'instagram',
        name: 'Instagram',
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
                        <Badge v-if="item.badge" :variant="item.badge.variant" class="absolute top-3 end-3">{{ item.badge.label }}</Badge>
                        <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                            <img :src="item.logo" :alt="item.name" class="size-14" />
                            <p class="font-medium">{{ item.name }}</p>
                            <p class="text-muted-foreground line-clamp-2 text-sm">{{ item.tagline }}</p>
                        </div>
                        <Button variant="outline" class="mt-4 w-full" @click="openChannel(item)">
                            <Settings2 class="me-2 size-4" />
                            {{ t('agents.channels.setup') }}
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
                                    People can message your bot on Telegram and your agent replies automatically — billing, history, and guardrails
                                    all apply.
                                </p>
                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: 'Telegram' }) }}
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
                                        <i18n-t keypath="agents.channels.public_only" tag="span" scope="global">
                                            <template #channel>Telegram</template>
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
                                        <span>Open <span class="text-foreground font-medium">@BotFather</span> in Telegram</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>Send <span class="text-foreground font-mono">/newbot</span> and follow the prompts</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>Paste the token below — we connect it automatically</span>
                                    </li>
                                </ol>
                                <CredentialInput
                                    id="bot-token"
                                    v-model="telegramForm.bot_token"
                                    label="Bot token"
                                    :icon="KeyRound"
                                    placeholder="123456789:ABCdef…"
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

                                <CopyField id="slack-webhook-url" label="Request URL" :icon="Link2" :value="props.slack.webhook_url" />

                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            In
                                            <a
                                                href="https://api.slack.com/apps"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >your Slack app</a
                                            >, open <span class="text-foreground font-medium">Event Subscriptions</span>, enable it, and paste the
                                            Request URL above
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span
                                            >Subscribe to the bot events <span class="text-foreground font-mono">app_mention</span> and
                                            <span class="text-foreground font-mono">message.im</span>, then save</span
                                        >
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span
                                            >Invite the bot to a channel with <span class="text-foreground font-mono">/invite</span> and @mention it —
                                            the reply streams in live</span
                                        >
                                    </li>
                                </ol>

                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: 'Slack' }) }}
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
                                        Slack is available for public agents only. Make this agent public on its
                                        <Link
                                            :href="route('agents.identity', props.agent.external_id)"
                                            class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                            >Security</Link
                                        >
                                        page, then connect it here.
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
                                            Create an app at
                                            <a
                                                href="https://api.slack.com/apps"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >api.slack.com/apps</a
                                            >
                                            in your workspace
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span
                                            >Under <span class="text-foreground font-medium">OAuth &amp; Permissions</span>, add the bot scopes
                                            <span class="text-foreground font-mono">app_mentions:read</span>,
                                            <span class="text-foreground font-mono">chat:write</span>,
                                            <span class="text-foreground font-mono">im:history</span>, then install the app and copy the bot
                                            token</span
                                        >
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span
                                            >Copy the signing secret from <span class="text-foreground font-medium">Basic Information</span> and paste
                                            both below</span
                                        >
                                    </li>
                                </ol>
                                <CredentialInput
                                    id="slack-bot-token"
                                    v-model="slackForm.bot_token"
                                    label="Bot token"
                                    :icon="KeyRound"
                                    placeholder="xoxb-…"
                                    :error="slackForm.errors.bot_token"
                                />
                                <CredentialInput
                                    id="slack-signing-secret"
                                    v-model="slackForm.signing_secret"
                                    label="Signing secret"
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

                                <CopyField id="messenger-webhook-url" label="Callback URL" :icon="Link2" :value="props.messenger.webhook_url" />
                                <CopyField
                                    id="messenger-verify-token"
                                    label="Verify token"
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
                                            In
                                            <a
                                                href="https://developers.facebook.com/apps"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >your Meta app</a
                                            >, open <span class="text-foreground font-medium">Messenger → Settings → Webhooks</span> and paste the
                                            Callback URL + verify token above
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>Subscribe the webhook to the <span class="text-foreground font-mono">messages</span> field</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>Message your page on Messenger — your agent replies automatically</span>
                                    </li>
                                </ol>

                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: 'Messenger' }) }}
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
                                        Messenger is available for public agents only. Make this agent public on its
                                        <Link
                                            :href="route('agents.identity', props.agent.external_id)"
                                            class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                            >Security</Link
                                        >
                                        page, then connect it here.
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
                                            Create an app at
                                            <a
                                                href="https://developers.facebook.com/apps"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >developers.facebook.com</a
                                            >
                                            and add the <span class="text-foreground font-medium">Messenger</span> product
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span
                                            >Under <span class="text-foreground font-medium">Messenger → API settings</span>, connect your Facebook
                                            Page and generate a page access token</span
                                        >
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span
                                            >Copy the app secret from <span class="text-foreground font-medium">App settings → Basic</span> and paste
                                            both below</span
                                        >
                                    </li>
                                </ol>
                                <CredentialInput
                                    id="messenger-page-token"
                                    v-model="messengerForm.page_access_token"
                                    label="Page access token"
                                    :icon="KeyRound"
                                    placeholder="EAAG…"
                                    :error="messengerForm.errors.page_access_token"
                                />
                                <CredentialInput
                                    id="messenger-app-secret"
                                    v-model="messengerForm.app_secret"
                                    label="App secret"
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

                                <CopyField id="whatsapp-webhook-url" label="Callback URL" :icon="Link2" :value="props.whatsapp.webhook_url" />
                                <CopyField id="whatsapp-verify-token" label="Verify token" :icon="ShieldCheck" :value="props.whatsapp.verify_token" />

                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            In
                                            <a
                                                href="https://developers.facebook.com/apps"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >your Meta app</a
                                            >, open <span class="text-foreground font-medium">WhatsApp → Configuration</span> and paste the Callback
                                            URL + verify token above
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>Subscribe the webhook to the <span class="text-foreground font-mono">messages</span> field</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>Message your number on WhatsApp — your agent replies automatically</span>
                                    </li>
                                </ol>
                                <p class="text-muted-foreground text-xs">
                                    Need more detail? Follow the
                                    <a
                                        href="https://docs.chatvia.ai/guide/channels/whatsapp"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                        >step-by-step guide</a
                                    >
                                    with screenshots.
                                </p>

                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: 'WhatsApp' }) }}
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
                                        WhatsApp is available for public agents only. Make this agent public on its
                                        <Link
                                            :href="route('agents.identity', props.agent.external_id)"
                                            class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                            >Security</Link
                                        >
                                        page, then connect it here.
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
                                            Create an app at
                                            <a
                                                href="https://developers.facebook.com/apps"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >developers.facebook.com</a
                                            >
                                            with the <span class="text-foreground font-medium">Connect with customers through WhatsApp</span> use case
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span
                                            >Complete <span class="text-foreground font-medium">Use cases → Customize → Basic setup</span>, then copy
                                            the phone number ID, WhatsApp Business Account ID, and an access token</span
                                        >
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span
                                            >Copy the app secret from <span class="text-foreground font-medium">App settings → Basic</span> and paste
                                            everything below</span
                                        >
                                    </li>
                                </ol>
                                <p class="text-muted-foreground text-xs">
                                    Need more detail? Follow the
                                    <a
                                        href="https://docs.chatvia.ai/guide/channels/whatsapp"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                        >step-by-step guide</a
                                    >
                                    with screenshots.
                                </p>
                                <CredentialInput
                                    id="whatsapp-phone-number-id"
                                    v-model="whatsappForm.phone_number_id"
                                    label="Phone number ID"
                                    :icon="Hash"
                                    placeholder="123456789012345"
                                    :error="whatsappForm.errors.phone_number_id"
                                />
                                <CredentialInput
                                    id="whatsapp-waba-id"
                                    v-model="whatsappForm.waba_id"
                                    label="WhatsApp Business Account ID"
                                    :icon="Building2"
                                    placeholder="123456789012345"
                                    :error="whatsappForm.errors.waba_id"
                                />
                                <CredentialInput
                                    id="whatsapp-access-token"
                                    v-model="whatsappForm.access_token"
                                    label="Access token"
                                    :icon="KeyRound"
                                    placeholder="EAAG…"
                                    :error="whatsappForm.errors.access_token"
                                />
                                <CredentialInput
                                    id="whatsapp-app-secret"
                                    v-model="whatsappForm.app_secret"
                                    label="App secret"
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
                                        <p class="text-muted-foreground truncate text-sm">App {{ props.msteams.app_id }}</p>
                                    </div>
                                </div>

                                <CopyField id="msteams-webhook-url" label="Messaging endpoint" :icon="Link2" :value="props.msteams.webhook_url" />

                                <ol class="text-muted-foreground space-y-2 text-xs">
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >1</span
                                        >
                                        <span>
                                            In
                                            <a
                                                href="https://portal.azure.com"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >the Azure portal</a
                                            >, open your bot's <span class="text-foreground font-medium">Configuration</span> page and paste the
                                            messaging endpoint above
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span
                                            >Under <span class="text-foreground font-medium">Channels</span>, add
                                            <span class="text-foreground font-medium">Microsoft Teams</span></span
                                        >
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>
                                            <a
                                                :href="`https://teams.microsoft.com/l/chat/0/0?users=28:${props.msteams.app_id}`"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >Open a chat with your bot in Teams</a
                                            >
                                            — the reply streams in live
                                        </span>
                                    </li>
                                </ol>

                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: 'Microsoft Teams' }) }}
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
                                        Microsoft Teams is available for public agents only. Make this agent public on its
                                        <Link
                                            :href="route('agents.identity', props.agent.external_id)"
                                            class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                            >Security</Link
                                        >
                                        page, then connect it here.
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
                                            Create an <span class="text-foreground font-medium">Azure Bot</span> resource at
                                            <a
                                                href="https://portal.azure.com"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >portal.azure.com</a
                                            >
                                            (Multi Tenant or Single Tenant)
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span
                                            >Copy the <span class="text-foreground font-medium">Microsoft App ID</span> from Configuration, then
                                            create a client secret under
                                            <span class="text-foreground font-medium">Manage password → Certificates &amp; secrets</span></span
                                        >
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span
                                            >Paste both below — for a Single Tenant bot also add your
                                            <span class="text-foreground font-medium">Directory (tenant) ID</span></span
                                        >
                                    </li>
                                </ol>
                                <CredentialInput
                                    id="msteams-app-id"
                                    v-model="msteamsForm.app_id"
                                    label="Microsoft App ID"
                                    :icon="Fingerprint"
                                    placeholder="00000000-0000-0000-0000-000000000000"
                                    :error="msteamsForm.errors.app_id"
                                />
                                <CredentialInput
                                    id="msteams-app-secret"
                                    v-model="msteamsForm.app_secret"
                                    label="Client secret"
                                    :icon="Lock"
                                    secret
                                    :error="msteamsForm.errors.app_secret"
                                />
                                <CredentialInput
                                    id="msteams-tenant-id"
                                    v-model="msteamsForm.tenant_id"
                                    label="Tenant ID"
                                    hint="(Single Tenant bots only)"
                                    :icon="Building2"
                                    placeholder="Leave empty for Multi Tenant"
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

                                <CopyField id="instagram-webhook-url" label="Callback URL" :icon="Link2" :value="props.instagram.webhook_url" />
                                <CopyField
                                    id="instagram-verify-token"
                                    label="Verify token"
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
                                            In
                                            <a
                                                href="https://developers.facebook.com/apps"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >your Meta app</a
                                            >, open
                                            <span class="text-foreground font-medium"
                                                >Instagram → API setup with Instagram login → Configure webhooks</span
                                            >
                                            and paste the Callback URL + verify token above
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span>Subscribe the webhook to the <span class="text-foreground font-mono">messages</span> field</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span
                                            >In the Instagram app, allow message access under
                                            <span class="text-foreground font-medium"
                                                >Settings → Messages and story replies → Message controls → Connected tools</span
                                            >, then DM your account</span
                                        >
                                    </li>
                                </ol>

                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    {{ t('agents.channels.private_banner', { channel: 'Instagram' }) }}
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
                                        Instagram is available for public agents only. Make this agent public on its
                                        <Link
                                            :href="route('agents.identity', props.agent.external_id)"
                                            class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                            >Security</Link
                                        >
                                        page, then connect it here.
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
                                            Create an app at
                                            <a
                                                href="https://developers.facebook.com/apps"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-foreground font-medium underline underline-offset-4 hover:no-underline"
                                                >developers.facebook.com</a
                                            >
                                            and add the <span class="text-foreground font-medium">Instagram</span> product — your Instagram account
                                            must be a professional (business or creator) account
                                        </span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >2</span
                                        >
                                        <span
                                            >Under <span class="text-foreground font-medium">Instagram → API setup with Instagram login</span>,
                                            generate an access token for your account</span
                                        >
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span
                                            class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                            >3</span
                                        >
                                        <span>Copy the app secret from the same page and paste both below</span>
                                    </li>
                                </ol>
                                <CredentialInput
                                    id="instagram-access-token"
                                    v-model="instagramForm.access_token"
                                    label="Access token"
                                    :icon="KeyRound"
                                    placeholder="IGAA…"
                                    :error="instagramForm.errors.access_token"
                                />
                                <CredentialInput
                                    id="instagram-app-secret"
                                    v-model="instagramForm.app_secret"
                                    label="App secret"
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
