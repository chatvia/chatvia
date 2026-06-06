<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Link2, Link2Off, Share2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SocialAccount {
    provider: 'google' | 'microsoft' | 'slack' | 'github';
    label: string;
    connected: boolean;
    connected_at: string | null;
}

defineProps<{
    accounts: SocialAccount[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Social accounts', href: '/settings/social' }];

const page = usePage<SharedData>();
const status = computed(() => page.props.flash?.success ?? undefined);
const providerError = computed(() => page.props.errors?.provider);

const accountToDisconnect = ref<SocialAccount | null>(null);
const dialogOpen = ref(false);

const connect = (account: SocialAccount) => {
    window.location.href = route('settings.social.connect', { provider: account.provider });
};

const disconnect = (account: SocialAccount) => {
    accountToDisconnect.value = account;
    dialogOpen.value = true;
};

const confirmDisconnect = () => {
    const target = accountToDisconnect.value;
    dialogOpen.value = false;
    if (!target) return;
    router.delete(route('settings.social.disconnect', { provider: target.provider }), {
        preserveScroll: true,
    });
};

const providerSvg = (provider: SocialAccount['provider']): string => {
    switch (provider) {
        case 'google':
            return `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>`;
        case 'microsoft':
            return `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11.4 11.4H0V0h11.4v11.4z" fill="#F25022"/><path d="M24 11.4H12.6V0H24v11.4z" fill="#7FBA00"/><path d="M11.4 24H0V12.6h11.4V24z" fill="#00A4EF"/><path d="M24 24H12.6V12.6H24V24z" fill="#FFB900"/></svg>`;
        case 'slack':
            return `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5.04 15.16a2.52 2.52 0 1 1-2.52-2.52h2.52v2.52zm1.27 0a2.52 2.52 0 1 1 5.04 0v6.32a2.52 2.52 0 1 1-5.04 0v-6.32z" fill="#E01E5A"/><path d="M8.83 5.04a2.52 2.52 0 1 1 2.52-2.52v2.52H8.83zm0 1.27a2.52 2.52 0 1 1 0 5.04H2.52a2.52 2.52 0 1 1 0-5.04h6.31z" fill="#36C5F0"/><path d="M18.96 8.83a2.52 2.52 0 1 1 2.52 2.52h-2.52V8.83zm-1.27 0a2.52 2.52 0 1 1-5.04 0V2.52a2.52 2.52 0 1 1 5.04 0v6.31z" fill="#2EB67D"/><path d="M15.17 18.96a2.52 2.52 0 1 1-2.52 2.52v-2.52h2.52zm0-1.27a2.52 2.52 0 1 1 0-5.04h6.31a2.52 2.52 0 1 1 0 5.04h-6.31z" fill="#ECB22E"/></svg>`;
        case 'github':
            return '<svg viewBox="0 -0.5 25 25" xmlns="http://www.w3.org/2000/svg"><path d="m12.301 0h.093c2.242 0 4.34.613 6.137 1.68l-.055-.031c1.871 1.094 3.386 2.609 4.449 4.422l.031.058c1.04 1.769 1.654 3.896 1.654 6.166 0 5.406-3.483 10-8.327 11.658l-.087.026c-.063.02-.135.031-.209.031-.162 0-.312-.054-.433-.144l.002.001c-.128-.115-.208-.281-.208-.466 0-.005 0-.01 0-.014v.001q0-.048.008-1.226t.008-2.154c.007-.075.011-.161.011-.249 0-.792-.323-1.508-.844-2.025.618-.061 1.176-.163 1.718-.305l-.076.017c.573-.16 1.073-.373 1.537-.642l-.031.017c.508-.28.938-.636 1.292-1.058l.006-.007c.372-.476.663-1.036.84-1.645l.009-.035c.209-.683.329-1.468.329-2.281 0-.045 0-.091-.001-.136v.007c0-.022.001-.047.001-.072 0-1.248-.482-2.383-1.269-3.23l.003.003c.168-.44.265-.948.265-1.479 0-.649-.145-1.263-.404-1.814l.011.026c-.115-.022-.246-.035-.381-.035-.334 0-.649.078-.929.216l.012-.005c-.568.21-1.054.448-1.512.726l.038-.022-.609.384c-.922-.264-1.981-.416-3.075-.416s-2.153.152-3.157.436l.081-.02q-.256-.176-.681-.433c-.373-.214-.814-.421-1.272-.595l-.066-.022c-.293-.154-.64-.244-1.009-.244-.124 0-.246.01-.364.03l.013-.002c-.248.524-.393 1.139-.393 1.788 0 .531.097 1.04.275 1.509l-.01-.029c-.785.844-1.266 1.979-1.266 3.227 0 .025 0 .051.001.076v-.004c-.001.039-.001.084-.001.13 0 .809.12 1.591.344 2.327l-.015-.057c.189.643.476 1.202.85 1.693l-.009-.013c.354.435.782.793 1.267 1.062l.022.011c.432.252.933.465 1.46.614l.046.011c.466.125 1.024.227 1.595.284l.046.004c-.431.428-.718 1-.784 1.638l-.001.012c-.207.101-.448.183-.699.236l-.021.004c-.256.051-.549.08-.85.08-.022 0-.044 0-.066 0h.003c-.394-.008-.756-.136-1.055-.348l.006.004c-.371-.259-.671-.595-.881-.986l-.007-.015c-.198-.336-.459-.614-.768-.827l-.009-.006c-.225-.169-.49-.301-.776-.38l-.016-.004-.32-.048c-.023-.002-.05-.003-.077-.003-.14 0-.273.028-.394.077l.007-.003q-.128.072-.08.184c.039.086.087.16.145.225l-.001-.001c.061.072.13.135.205.19l.003.002.112.08c.283.148.516.354.693.603l.004.006c.191.237.359.505.494.792l.01.024.16.368c.135.402.38.738.7.981l.005.004c.3.234.662.402 1.057.478l.016.002c.33.064.714.104 1.106.112h.007c.045.002.097.002.15.002.261 0 .517-.021.767-.062l-.027.004.368-.064q0 .609.008 1.418t.008.873v.014c0 .185-.08.351-.208.466h-.001c-.119.089-.268.143-.431.143-.075 0-.147-.011-.214-.032l.005.001c-4.929-1.689-8.409-6.283-8.409-11.69 0-2.268.612-4.393 1.681-6.219l-.032.058c1.094-1.871 2.609-3.386 4.422-4.449l.058-.031c1.739-1.034 3.835-1.645 6.073-1.645h.098-.005zm-7.64 17.666q.048-.112-.112-.192-.16-.048-.208.032-.048.112.112.192.144.096.208-.032zm.497.545q.112-.08-.032-.256-.16-.144-.256-.048-.112.08.032.256.159.157.256.047zm.48.72q.144-.112 0-.304-.128-.208-.272-.096-.144.08 0 .288t.272.112zm.672.673q.128-.128-.064-.304-.192-.192-.32-.048-.144.128.064.304.192.192.32.044zm.913.4q.048-.176-.208-.256-.24-.064-.304.112t.208.24q.24.097.304-.096zm1.009.08q0-.208-.272-.176-.256 0-.256.176 0 .208.272.176.256.001.256-.175zm.929-.16q-.032-.176-.288-.144-.256.048-.224.24t.288.128.225-.224z"/></svg>';
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Social accounts" />

        <SettingsLayout width="wide">
            <div class="space-y-8">
                <div class="flex flex-col space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-primary/10 rounded-full p-2">
                            <Share2 class="text-primary size-6" />
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight">Social accounts</h1>
                    </div>
                    <p class="text-muted-foreground">Connect Google, Microsoft, or Slack to sign in with one click and keep your accounts in sync.</p>
                </div>

                <Separator />

                <Alert v-if="status">
                    <CheckCircle2 class="size-4" />
                    <AlertDescription>{{ status }}</AlertDescription>
                </Alert>

                <Alert v-if="providerError" variant="destructive">
                    <AlertDescription>{{ providerError }}</AlertDescription>
                </Alert>

                <div class="space-y-3">
                    <div v-for="account in accounts" :key="account.provider" class="flex items-center justify-between gap-4 rounded-lg border p-4">
                        <div class="flex items-center gap-3">
                            <div class="size-9 shrink-0" v-html="providerSvg(account.provider)" />
                            <div>
                                <p class="font-medium">{{ account.label }}</p>
                                <p class="text-muted-foreground text-xs">
                                    <span v-if="account.connected">
                                        Connected
                                        <span v-if="account.connected_at"> since {{ new Date(account.connected_at).toLocaleDateString() }} </span>
                                    </span>
                                    <span v-else>Not connected</span>
                                </p>
                            </div>
                        </div>

                        <Button v-if="account.connected" variant="outline" @click="disconnect(account)">
                            <Link2Off class="mr-2 size-4" />
                            Disconnect
                        </Button>
                        <Button v-else @click="connect(account)">
                            <Link2 class="mr-2 size-4" />
                            Connect
                        </Button>
                    </div>
                </div>
            </div>
        </SettingsLayout>

        <AlertDialog :open="dialogOpen" @update:open="(v) => (dialogOpen = v)">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Disconnect {{ accountToDisconnect?.label }}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        You'll no longer be able to sign in with this account. You can reconnect it at any time.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="confirmDisconnect">
                        Disconnect
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
