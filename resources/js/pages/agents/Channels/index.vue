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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    Command,
    Fingerprint,
    Hash,
    KeyRound,
    Link2,
    LoaderCircle,
    Lock,
    Plug,
    Plus,
    Settings2,
    ShieldCheck,
    Trash2,
    Unplug,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import CopyField from './CopyField.vue';
import CredentialInput from './CredentialInput.vue';

const { t } = useI18n();

interface Props {
    agent: { external_id: string; name: string; is_public: boolean };
    telegram: {
        connected: boolean;
        bot_username: string | null;
        enabled: boolean;
        commands: { command: string; description: string; type: 'message' | 'prompt'; value: string; builtin: boolean }[];
    };
    slack: { connected: boolean; team_name: string | null; webhook_url: string | null; enabled: boolean };
    messenger: { connected: boolean; page_name: string | null; enabled: boolean; pick: { id: string; name: string }[] | null };
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
    messengerSelectForm.reset();
    messengerSelectForm.clearErrors();
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

// ── Messenger connect (Facebook Login for Business) ---------------------------
// Connecting is a full-page hop to Facebook's consent screen (not an Inertia
// XHR): redirect → consent → our callback drops the granted Pages into `pick`
// → the workspace picks one below. Callback errors come back as a shared page
// error (no form owns them).
const page = usePage();
const messengerError = computed<string | undefined>(() => (page.props.errors as Record<string, string> | undefined)?.messenger);

const connectMessenger = () => {
    window.location.href = route('agents.channels.messenger.redirect', props.agent.external_id);
};

const messengerSelectForm = useForm({ page_id: props.messenger.pick?.[0]?.id ?? '' });

const selectMessengerPage = () => {
    messengerSelectForm.post(route('agents.channels.messenger.select', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
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

// ── Telegram commands ---------------------------------------------------------
// Each command is a saved message or a prompt run through the agent. Built-ins
// (/start, /reset, /help) arrive pre-filled and pinned; customs can be added or
// removed. Same draft → dialog → commit-on-Save pattern as the Guardrails editor.
interface TelegramCommand {
    command: string;
    description: string;
    type: 'message' | 'prompt';
    value: string;
    builtin: boolean;
}

const commandsOpen = ref(false);
const commandsForm = useForm<{ commands: TelegramCommand[] }>({ commands: [] });

const openCommands = () => {
    commandsForm.clearErrors();
    commandsForm.commands = props.telegram.commands.map((c) => ({ ...c }));
    commandsOpen.value = true;
};
const addCommand = () => commandsForm.commands.push({ command: '', description: '', type: 'prompt', value: '', builtin: false });
const removeCommand = (index: number) => commandsForm.commands.splice(index, 1);
const saveCommands = () => {
    commandsForm
        // Drop fully-blank custom rows so a stray empty row never blocks the save;
        // built-ins always go through, and the server validates the rest.
        .transform((data) => ({
            commands: data.commands.filter((c) => c.builtin || c.command.trim() || c.description.trim() || c.value.trim()),
        }))
        .put(route('agents.channels.telegram.commands', props.agent.external_id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                commandsOpen.value = false;
                toast.success(t('agents.channels.setup.telegram.commands.saved_toast'));
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

                                <!-- Custom commands -->
                                <div class="rounded-lg border p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium">{{ t('agents.channels.setup.telegram.commands.title') }}</p>
                                            <p class="text-muted-foreground truncate text-xs">
                                                {{
                                                    props.telegram.commands.length
                                                        ? props.telegram.commands.map((c) => '/' + c.command).join(', ')
                                                        : t('agents.channels.setup.telegram.commands.empty')
                                                }}
                                            </p>
                                        </div>
                                        <Button v-if="canManage" variant="outline" size="sm" class="shrink-0" @click="openCommands">
                                            <Command class="me-2 size-4" />
                                            {{ t('agents.channels.setup.telegram.commands.manage') }}
                                        </Button>
                                    </div>
                                    <p class="text-muted-foreground mt-3 text-xs">
                                        {{ t('agents.channels.setup.telegram.commands.builtins_note') }}
                                    </p>
                                </div>

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
                                <div
                                    v-if="messengerError"
                                    class="border-destructive/30 bg-destructive/10 text-destructive rounded-md border p-3 text-xs"
                                >
                                    {{ messengerError }}
                                </div>

                                <!-- Page picker — the Pages Facebook handed back after consent -->
                                <template v-if="props.messenger.pick && props.messenger.pick.length">
                                    <p class="text-sm font-medium">{{ t('agents.channels.setup.messenger.pick_title') }}</p>
                                    <div class="space-y-2">
                                        <label
                                            v-for="fbPage in props.messenger.pick"
                                            :key="fbPage.id"
                                            class="hover:bg-muted/50 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition"
                                            :class="messengerSelectForm.page_id === fbPage.id ? 'border-foreground/40 bg-muted/50' : ''"
                                        >
                                            <input
                                                v-model="messengerSelectForm.page_id"
                                                type="radio"
                                                :value="fbPage.id"
                                                class="accent-foreground size-4"
                                            />
                                            <span class="truncate text-sm font-medium">{{ fbPage.name }}</span>
                                        </label>
                                    </div>
                                    <div class="border-t pt-5">
                                        <Button
                                            class="w-full"
                                            :disabled="!messengerSelectForm.page_id || messengerSelectForm.processing"
                                            @click="selectMessengerPage"
                                        >
                                            <LoaderCircle v-if="messengerSelectForm.processing" class="me-2 size-4 animate-spin" />
                                            <Plug v-else class="me-2 size-4" />
                                            {{ t('agents.channels.setup.messenger.pick_button') }}
                                        </Button>
                                    </div>
                                </template>

                                <!-- Kick off the OAuth flow -->
                                <template v-else>
                                    <p class="text-muted-foreground text-sm">{{ t('agents.channels.setup.messenger.oauth_intro') }}</p>
                                    <div class="border-t pt-5">
                                        <Button class="w-full" @click="connectMessenger">
                                            <Plug class="me-2 size-4" />
                                            {{ t('agents.channels.setup.messenger.connect_facebook') }}
                                        </Button>
                                    </div>
                                </template>
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

        <!-- Telegram commands -->
        <Dialog v-model:open="commandsOpen">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ t('agents.channels.setup.telegram.commands.dialog_title') }}</DialogTitle>
                    <DialogDescription>{{ t('agents.channels.setup.telegram.commands.dialog_description') }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-3">
                    <div v-for="(command, index) in commandsForm.commands" :key="index" class="space-y-2 rounded-lg border p-3">
                        <div class="flex items-center gap-2">
                            <!-- Command name: read-only for built-ins, editable for custom -->
                            <div
                                v-if="command.builtin"
                                class="bg-muted text-foreground/80 flex h-9 w-32 shrink-0 items-center rounded-md px-3 font-mono text-sm"
                            >
                                /{{ command.command }}
                            </div>
                            <div v-else class="relative w-32 shrink-0">
                                <span class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-sm">/</span>
                                <Input
                                    v-model="command.command"
                                    :placeholder="t('agents.channels.setup.telegram.commands.command_placeholder')"
                                    class="ps-6 font-mono"
                                />
                            </div>

                            <!-- Description (shown in Telegram's command menu) -->
                            <Input
                                v-model="command.description"
                                :placeholder="t('agents.channels.setup.telegram.commands.description_placeholder')"
                                class="flex-1"
                            />

                            <!-- Mode: prompt (run through agent) vs message (canned) -->
                            <Select v-model="command.type">
                                <SelectTrigger class="w-36 shrink-0"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="prompt">{{ t('agents.channels.setup.telegram.commands.mode_prompt') }}</SelectItem>
                                    <SelectItem value="message">{{ t('agents.channels.setup.telegram.commands.mode_message') }}</SelectItem>
                                </SelectContent>
                            </Select>

                            <Button
                                v-if="!command.builtin"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-muted-foreground hover:text-destructive shrink-0"
                                :aria-label="t('agents.channels.setup.telegram.commands.remove')"
                                @click="removeCommand(index)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                            <Badge v-else variant="secondary" class="shrink-0">
                                {{ t('agents.channels.setup.telegram.commands.builtin_badge') }}
                            </Badge>
                        </div>

                        <Textarea
                            v-model="command.value"
                            :rows="2"
                            :placeholder="
                                command.type === 'prompt'
                                    ? t('agents.channels.setup.telegram.commands.prompt_placeholder')
                                    : t('agents.channels.setup.telegram.commands.message_placeholder')
                            "
                        />

                        <p class="text-muted-foreground text-xs">
                            {{
                                command.command === 'reset'
                                    ? t('agents.channels.setup.telegram.commands.reset_hint')
                                    : command.command === 'help'
                                      ? t('agents.channels.setup.telegram.commands.help_hint')
                                      : t('agents.channels.setup.telegram.commands.name_token_hint')
                            }}
                        </p>
                    </div>

                    <p v-for="(message, key) in commandsForm.errors" :key="key" class="text-destructive text-xs">{{ message }}</p>
                </div>

                <Button type="button" variant="outline" size="sm" class="w-fit" @click="addCommand">
                    <Plus class="me-2 size-4" />
                    {{ t('agents.channels.setup.telegram.commands.add') }}
                </Button>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="commandsOpen = false">
                        <X class="me-2 size-4" />
                        {{ t('agents.channels.setup.telegram.commands.cancel') }}
                    </Button>
                    <Button type="button" :disabled="commandsForm.processing" @click="saveCommands">
                        <LoaderCircle v-if="commandsForm.processing" class="me-2 size-4 animate-spin" />
                        <Check v-else class="me-2 size-4" />
                        {{ t('agents.channels.setup.telegram.commands.save') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
