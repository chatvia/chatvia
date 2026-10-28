<script setup lang="ts">
// The full notification center: every notification of the last days (All /
// Unread), newest activity first, with "Load more", "Mark all as read" and a
// link to the notification settings. It updates live like the bell.
import NotificationItem from '@/dashboard/components/notifications/NotificationItem.vue';
import { Button } from '@/shared/ui/button';
import { Separator } from '@/shared/ui/separator';
import { Tabs, TabsList, TabsTrigger } from '@/shared/ui/tabs';
import type { NotificationFeed, NotificationFilter } from '@/dashboard/api/notifications';
import { unreadCount, visitNotification, type UserNotification } from '@/shared/composables/notifications/notificationStore';
import { useNotificationList } from '@/dashboard/composables/notifications/useNotificationList';
import AppLayout from '@/dashboard/layouts/AppLayout.vue';
import account from '@/routes/account';
import notifications from '@/routes/notifications';
import { type BreadcrumbItem } from '@/shared/types';
import { Head, Link } from '@inertiajs/vue3';
import { Bell, CheckCheck, Loader2, Settings } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    filter: NotificationFilter;
    feed: NotificationFeed;
}>();

const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [{ title: t('notifications.title'), href: notifications.index.url() }];

const filter = ref<NotificationFilter>(props.filter);
const list = useNotificationList(filter, props.feed);

watch(filter, (value) => {
    // Keep the address in step, so a reload shows the same tab.
    window.history.replaceState(window.history.state, '', notifications.index.url(value === 'unread' ? { query: { filter: value } } : undefined));
    void list.load();
});

const visible = computed(() => (filter.value === 'unread' ? list.items.value.filter((item) => !item.read) : list.items.value));

function openNotification(notification: UserNotification): void {
    void list.markRead(notification);
    visitNotification(notification);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="t('notifications.title')" />

        <div class="mx-auto w-full max-w-3xl px-4 py-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Bell class="size-6" />
                        {{ t('notifications.title') }}
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('notifications.page_description') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" :disabled="unreadCount === 0" @click="list.markAllRead()">
                        <CheckCheck class="me-1 size-4" />
                        {{ t('notifications.mark_all_read') }}
                    </Button>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="account.notifications.edit.url()">
                            <Settings class="me-1 size-4" />
                            {{ t('notifications.settings') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <Separator class="my-6" />

            <Tabs v-model="filter" class="mb-4">
                <TabsList>
                    <TabsTrigger value="all">{{ t('notifications.filter_all') }}</TabsTrigger>
                    <TabsTrigger value="unread">
                        {{ t('notifications.filter_unread') }}
                        <span
                            v-if="unreadCount > 0"
                            class="ms-1.5 rounded-full bg-red-500 px-1.5 text-[10px] leading-4 font-semibold text-white tabular-nums"
                        >
                            {{ unreadCount > 99 ? '99+' : unreadCount }}
                        </span>
                    </TabsTrigger>
                </TabsList>
            </Tabs>

            <div class="bg-card overflow-hidden rounded-lg border">
                <div
                    v-if="list.loading.value && visible.length === 0"
                    class="text-muted-foreground flex items-center justify-center gap-2 py-16 text-sm"
                >
                    <Loader2 class="size-4 animate-spin" />
                </div>

                <div v-else-if="visible.length === 0" class="px-6 py-16 text-center">
                    <div class="bg-muted mx-auto flex size-12 items-center justify-center rounded-full">
                        <Bell class="text-muted-foreground size-5" />
                    </div>
                    <p class="mt-3 text-sm font-medium">
                        {{ filter === 'unread' ? t('notifications.empty_unread') : t('notifications.empty_title') }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('notifications.empty_body') }}</p>
                </div>

                <div v-else class="divide-y">
                    <NotificationItem
                        v-for="notification in visible"
                        :key="notification.id"
                        :notification="notification"
                        show-mark-read
                        @open="openNotification"
                        @mark-read="list.markRead"
                    />
                </div>
            </div>

            <p v-if="list.failed.value" class="text-destructive mt-3 text-center text-sm">{{ t('notifications.load_failed') }}</p>

            <div v-if="list.nextCursor.value" class="mt-4 flex justify-center">
                <Button variant="outline" size="sm" :disabled="list.loading.value" @click="list.loadMore()">
                    <Loader2 v-if="list.loading.value" class="me-1 size-4 animate-spin" />
                    {{ t('notifications.load_more') }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
