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
    provider: 'google' | 'microsoft' | 'slack';
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

const connect = (account: SocialAccount) => {
    window.location.href = route('settings.social.connect', { provider: account.provider });
};

const disconnect = (account: SocialAccount) => {
    accountToDisconnect.value = account;
};

const confirmDisconnect = () => {
    const target = accountToDisconnect.value;
    accountToDisconnect.value = null;
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

        <AlertDialog :open="accountToDisconnect !== null" @update:open="(v) => !v && (accountToDisconnect = null)">
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
