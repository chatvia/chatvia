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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, ChevronRight, LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    agent: { external_id: string; name: string; is_public: boolean };
    telegram: { connected: boolean; bot_username: string | null; enabled: boolean };
}

const props = defineProps<Props>();

const { hasPermission } = useTeamPermissions();
const canManage = computed(() => hasPermission('agent.setup'));

type ChannelKind = 'telegram' | 'whatsapp' | 'slack' | 'messenger';

interface ChannelItem {
    kind: ChannelKind;
    name: string;
    tagline: string;
    logo: string;
    badge?: { label: string; variant: 'default' | 'secondary' | 'outline' };
    comingSoon?: boolean;
}

const items = computed<ChannelItem[]>(() => [
    {
        kind: 'telegram',
        name: 'Telegram',
        tagline: props.telegram.connected
            ? `Chatting as @${props.telegram.bot_username}`
            : 'Let people chat with your agent inside Telegram.',
        logo: '/images/logos/channels/telegram.svg',
        badge: props.telegram.connected ? { label: 'Connected', variant: 'default' } : undefined,
    },
    {
        kind: 'whatsapp',
        name: 'WhatsApp',
        tagline: 'Reach your agent from WhatsApp.',
        logo: '/images/logos/channels/whatsapp.svg',
        badge: { label: 'Soon', variant: 'outline' },
        comingSoon: true,
    },
    {
        kind: 'slack',
        name: 'Slack',
        tagline: 'Bring your agent into your Slack workspace.',
        logo: '/images/logos/channels/slack.svg',
        badge: { label: 'Soon', variant: 'outline' },
        comingSoon: true,
    },
    {
        kind: 'messenger',
        name: 'Messenger',
        tagline: 'Reach your agent from Facebook Messenger.',
        logo: '/images/logos/channels/messenger.svg',
        badge: { label: 'Soon', variant: 'outline' },
        comingSoon: true,
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
    form.reset();
    form.clearErrors();
    sheetOpen.value = true;
};

// ── Telegram connect / disconnect --------------------------------------------
const form = useForm({ bot_token: '' });

const connect = () => {
    form.post(route('agents.channels.telegram.connect', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            toast.success('Telegram connected');
        },
    });
};

const disconnectOpen = ref(false);

const disconnect = () => {
    router.delete(route('agents.channels.telegram.disconnect', props.agent.external_id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            disconnectOpen.value = false;
            toast.success('Telegram disconnected');
        },
    });
};
</script>

<template>
    <AgentLayout
        :agent="props.agent"
        title="Channels"
        description="Connect your agent to messaging apps so people can chat with it where they already are."
    >
        <Head :title="`Channels — ${props.agent.name}`" />

        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-4xl">
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="item in items"
                        :key="item.kind"
                        type="button"
                        class="group bg-card hover:border-foreground/20 hover:bg-accent/40 relative flex w-full cursor-pointer items-center gap-4 rounded-xl border p-4 text-left transition hover:shadow-sm"
                        @click="openChannel(item)"
                    >
                        <img
                            :src="item.logo"
                            :alt="item.name"
                            class="size-11 shrink-0"
                            :class="{ 'opacity-40 grayscale': item.comingSoon }"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate font-medium">{{ item.name }}</p>
                                <Badge v-if="item.badge" :variant="item.badge.variant">{{ item.badge.label }}</Badge>
                            </div>
                            <p class="text-muted-foreground mt-0.5 truncate text-sm">{{ item.tagline }}</p>
                        </div>
                        <ChevronRight class="text-muted-foreground/40 group-hover:text-muted-foreground size-4 shrink-0 transition" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Config sidebar -->
        <Sheet v-model:open="sheetOpen">
            <SheetContent class="w-full overflow-y-auto p-0 sm:max-w-md">
                <template v-if="selected">
                    <SheetHeader class="border-b p-6">
                        <div class="mb-1 flex items-center gap-3">
                            <img :src="selected.logo" :alt="selected.name" class="size-9" :class="{ 'opacity-40 grayscale': selected.comingSoon }" />
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
                                        <p class="text-sm font-medium">Connected</p>
                                        <p class="text-muted-foreground truncate text-sm">@{{ props.telegram.bot_username }}</p>
                                    </div>
                                </div>
                                <p class="text-muted-foreground text-sm">
                                    People can message your bot on Telegram and your agent replies automatically — billing, history, and guardrails all
                                    apply.
                                </p>
                                <div
                                    v-if="!props.agent.is_public"
                                    class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
                                >
                                    This agent is private, so it won’t answer on Telegram. Make it public again on the Security page, or disconnect.
                                </div>
                                <div v-if="canManage" class="border-t pt-5">
                                    <Button variant="outline" class="text-destructive hover:text-destructive w-full" @click="disconnectOpen = true">
                                        Disconnect
                                    </Button>
                                </div>
                            </template>

                            <!-- Protected agent: messaging channels are public-only -->
                            <template v-else-if="!props.agent.is_public">
                                <div class="bg-muted/40 rounded-lg border border-dashed p-5 text-center">
                                    <p class="text-sm font-medium">This agent is private</p>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        Telegram is available for public agents only. Make this agent public on its
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
                                <div class="space-y-1.5">
                                    <Label for="bot-token">Bot token</Label>
                                    <Input
                                        id="bot-token"
                                        v-model="form.bot_token"
                                        placeholder="123456789:ABCdef…"
                                        class="font-mono"
                                        autocomplete="off"
                                        @keydown.enter="connect"
                                    />
                                    <p v-if="form.errors.bot_token" class="text-destructive text-xs">{{ form.errors.bot_token }}</p>
                                    <ol v-else class="text-muted-foreground mt-1 space-y-2 text-xs">
                                        <li class="flex items-center gap-2.5">
                                            <span class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold">1</span>
                                            <span>Open <span class="text-foreground font-medium">@BotFather</span> in Telegram</span>
                                        </li>
                                        <li class="flex items-center gap-2.5">
                                            <span class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold">2</span>
                                            <span>Send <span class="text-foreground font-mono">/newbot</span> and follow the prompts</span>
                                        </li>
                                        <li class="flex items-center gap-2.5">
                                            <span class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold">3</span>
                                            <span>Paste the token here — we connect it automatically</span>
                                        </li>
                                    </ol>
                                </div>
                                <div class="border-t pt-5">
                                    <Button class="w-full" :disabled="form.processing || !form.bot_token" @click="connect">
                                        <LoaderCircle v-if="form.processing" class="mr-2 size-4 animate-spin" />
                                        Connect
                                    </Button>
                                </div>
                            </template>

                            <template v-else>
                                <p class="text-muted-foreground text-sm">You don’t have permission to manage this agent’s channels.</p>
                            </template>
                        </template>

                        <!-- Coming soon (WhatsApp / Slack / Messenger) -->
                        <template v-else-if="selected.comingSoon">
                            <div class="bg-muted/40 rounded-lg border border-dashed p-5 text-center">
                                <p class="text-sm font-medium">Coming soon</p>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    {{ selected.name }} support is on the way. Telegram is available today — and your agent will work the same way here once it
                                    lands.
                                </p>
                            </div>
                        </template>
                    </div>
                </template>
            </SheetContent>
        </Sheet>

        <!-- Disconnect confirmation -->
        <AlertDialog v-model:open="disconnectOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Disconnect Telegram</AlertDialogTitle>
                    <AlertDialogDescription>
                        People won’t be able to chat with this agent on Telegram anymore. You can reconnect with the same token later.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="disconnect">
                        Disconnect
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AgentLayout>
</template>
