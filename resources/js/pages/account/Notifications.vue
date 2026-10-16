<script setup lang="ts">
// The account's notification settings: per category, whether it reaches the
// bell, shows a desktop alert and plays a sound. Desktop alerts also need
// this browser's permission, asked here with a button (browsers only allow
// asking after a click).
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import {
    desktopAlertsSupported,
    desktopPermission,
    playChime,
    requestDesktopPermission,
} from '@/composables/notifications/notificationAlerts';
import type { NotificationPreferences } from '@/composables/notifications/notificationStore';
import AppLayout from '@/layouts/AppLayout.vue';
import AccountLayout from '@/layouts/account/Layout.vue';
import account from '@/routes/account';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Bell, BellRing, Gauge, GraduationCap, Headset, Monitor, ShieldAlert, UserPlus, Volume2, FileBarChart, type LucideIcon } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Category = Domain.Notification.Enums.NotificationCategory;
type Switch = 'bell' | 'desktop' | 'sound';

const props = defineProps<{
    preferences: NotificationPreferences;
}>();

const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [{ title: t('account.notifications.title'), href: account.notifications.edit.url() }];

interface CategoryRow {
    key: Category;
    icon: LucideIcon;
    label: string;
    description: string;
}

const categories: CategoryRow[] = [
    { key: 'handoffs', icon: Headset, label: t('account.notifications.categories.handoffs.label'), description: t('account.notifications.categories.handoffs.description') },
    { key: 'security', icon: ShieldAlert, label: t('account.notifications.categories.security.label'), description: t('account.notifications.categories.security.description') },
    { key: 'training', icon: GraduationCap, label: t('account.notifications.categories.training.label'), description: t('account.notifications.categories.training.description') },
    { key: 'leads', icon: UserPlus, label: t('account.notifications.categories.leads.label'), description: t('account.notifications.categories.leads.description') },
    { key: 'usage', icon: Gauge, label: t('account.notifications.categories.usage.label'), description: t('account.notifications.categories.usage.description') },
    { key: 'reports', icon: FileBarChart, label: t('account.notifications.categories.reports.label'), description: t('account.notifications.categories.reports.description') },
];

const switches: { key: Switch; icon: LucideIcon; label: string }[] = [
    { key: 'bell', icon: Bell, label: t('account.notifications.switches.bell') },
    { key: 'desktop', icon: Monitor, label: t('account.notifications.switches.desktop') },
    { key: 'sound', icon: Volume2, label: t('account.notifications.switches.sound') },
];

const form = useForm<NotificationPreferences>(JSON.parse(JSON.stringify(props.preferences)));

function setSwitch(category: Category, key: Switch, value: boolean): void {
    form[category][key] = value;
    // Desktop alerts and sounds come with the bell; without it they stop too.
    if (key === 'bell' && !value) {
        form[category].desktop = false;
        form[category].sound = false;
    }
}

function save(): void {
    form.patch(account.notifications.update.url(), { preserveScroll: true });
}

const permission = ref(desktopPermission());

async function allowDesktop(): Promise<void> {
    permission.value = await requestDesktopPermission();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('account.notifications.title')" />

        <AccountLayout width="wide">
            <div class="w-full space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <BellRing class="size-6" />
                        {{ t('account.notifications.heading') }}
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('account.notifications.description') }}</p>
                </div>

                <Separator />

                <!-- This browser: desktop permission and a test sound -->
                <div class="flex flex-col gap-4 rounded-lg border p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-md">
                            <Monitor class="text-muted-foreground size-5" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold">{{ t('account.notifications.browser.title') }}</p>
                            <p class="text-muted-foreground text-sm">
                                <template v-if="!desktopAlertsSupported()">{{ t('account.notifications.browser.unsupported') }}</template>
                                <template v-else-if="permission === 'granted'">{{ t('account.notifications.browser.granted') }}</template>
                                <template v-else-if="permission === 'denied'">{{ t('account.notifications.browser.denied') }}</template>
                                <template v-else>{{ t('account.notifications.browser.default') }}</template>
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Button v-if="permission === 'default'" size="sm" @click="allowDesktop">
                            {{ t('account.notifications.browser.allow') }}
                        </Button>
                        <Button variant="outline" size="sm" @click="playChime">
                            <Volume2 class="me-1 size-4" />
                            {{ t('account.notifications.browser.test_sound') }}
                        </Button>
                    </div>
                </div>

                <!-- Per category: bell, desktop alert, sound -->
                <form class="space-y-4" @submit.prevent="save">
                    <div class="overflow-hidden rounded-lg border">
                        <div class="bg-muted/40 text-muted-foreground hidden grid-cols-[1fr_repeat(3,5.5rem)] items-center gap-2 border-b px-5 py-2.5 text-xs font-medium sm:grid">
                            <span>{{ t('account.notifications.category') }}</span>
                            <span v-for="item in switches" :key="item.key" class="flex items-center justify-center gap-1">
                                <component :is="item.icon" class="size-3.5" />
                                {{ item.label }}
                            </span>
                        </div>

                        <div
                            v-for="category in categories"
                            :key="category.key"
                            class="grid grid-cols-1 gap-3 border-b px-5 py-4 last:border-b-0 sm:grid-cols-[1fr_repeat(3,5.5rem)] sm:items-center sm:gap-2"
                        >
                            <div class="flex items-start gap-3">
                                <component :is="category.icon" class="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                <div>
                                    <p class="text-sm font-medium">{{ category.label }}</p>
                                    <p class="text-muted-foreground text-xs">{{ category.description }}</p>
                                </div>
                            </div>
                            <label
                                v-for="item in switches"
                                :key="item.key"
                                class="flex items-center gap-2 ps-7 text-xs sm:justify-center sm:ps-0"
                            >
                                <Switch
                                    :model-value="form[category.key][item.key]"
                                    :disabled="item.key !== 'bell' && !form[category.key].bell"
                                    :aria-label="`${category.label}: ${item.label}`"
                                    @update:model-value="(value: boolean) => setSwitch(category.key, item.key, value)"
                                />
                                <span class="text-muted-foreground sm:sr-only">{{ item.label }}</span>
                            </label>
                        </div>
                    </div>

                    <p class="text-muted-foreground text-xs">{{ t('account.notifications.hint') }}</p>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing || !form.isDirty">
                            {{ form.processing ? t('common.saving') : t('common.save') }}
                        </Button>
                    </div>
                </form>
            </div>
        </AccountLayout>
    </AppLayout>
</template>
