<script setup lang="ts">
// The header's notification bell: a red badge with the unread count, and a
// popover with the latest notifications, "Mark all as read", a link to the
// notification settings and one to the full Notifications page. It also
// listens on the person's channel, so mount it once per page.
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import { unreadCount, type UserNotification } from '@/composables/notifications/notificationStore';
import { useNotificationList } from '@/composables/notifications/useNotificationList';
import { useNotificationStream } from '@/composables/notifications/useNotificationStream';
import account from '@/routes/account';
import notifications from '@/routes/notifications';
import { Link, router } from '@inertiajs/vue3';
import { Bell, BellRing, CheckCheck, Settings } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

useNotificationStream();

const open = ref(false);
const list = useNotificationList('all');

watch(open, (isOpen) => {
    if (isOpen) void list.load();
});

const badge = computed(() => (unreadCount.value > 99 ? '99+' : String(unreadCount.value)));

// A short ring on the badge when a new notification raises the count.
const ringing = ref(false);
let ringTimer: ReturnType<typeof setTimeout> | undefined;
watch(unreadCount, (count, previous) => {
    if (count <= previous) return;
    ringing.value = true;
    clearTimeout(ringTimer);
    ringTimer = setTimeout(() => (ringing.value = false), 2400);
});

function openNotification(notification: UserNotification): void {
    open.value = false;
    void list.markRead(notification);
    router.visit(notification.content.url);
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative h-9 w-9"
                :aria-label="unreadCount > 0 ? t('notifications.open_with_count', { count: unreadCount }, unreadCount) : t('notifications.open')"
            >
                <BellRing v-if="ringing" class="h-4 w-4 animate-pulse" />
                <Bell v-else class="h-4 w-4" />
                <span v-if="unreadCount > 0" class="absolute -end-0.5 -top-0.5 flex">
                    <span v-if="ringing" class="absolute inline-flex size-full animate-ping rounded-full bg-red-500 opacity-60" />
                    <span
                        class="ring-background relative flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] leading-none font-semibold text-white tabular-nums ring-2"
                    >
                        {{ badge }}
                    </span>
                </span>
            </Button>
        </PopoverTrigger>

        <PopoverContent align="end" :side-offset="8" class="w-[min(24rem,calc(100vw-1.5rem))] overflow-hidden p-0">
            <div class="flex items-center justify-between gap-2 border-b px-4 py-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold">{{ t('notifications.title') }}</h2>
                    <span v-if="unreadCount > 0" class="rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-medium text-red-600 dark:text-red-400">
                        {{ t('notifications.unread_count', { count: unreadCount }, unreadCount) }}
                    </span>
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        v-if="unreadCount > 0"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground h-7 gap-1 px-2 text-xs"
                        @click="list.markAllRead()"
                    >
                        <CheckCheck class="size-3.5" />
                        {{ t('notifications.mark_all_read') }}
                    </Button>
                    <Button variant="ghost" size="icon" class="text-muted-foreground size-7" as-child>
                        <Link :href="account.notifications.edit.url()" :aria-label="t('notifications.settings')" @click="open = false">
                            <Settings class="size-3.5" />
                        </Link>
                    </Button>
                </div>
            </div>

            <ScrollArea class="max-h-[min(28rem,70vh)]">
                <div v-if="list.loading.value && !list.loaded.value" class="space-y-1 p-2">
                    <div v-for="index in 4" :key="index" class="flex gap-3 px-2 py-2">
                        <Skeleton class="size-9 rounded-full" />
                        <div class="flex-1 space-y-2">
                            <Skeleton class="h-3.5 w-3/4" />
                            <Skeleton class="h-3 w-1/2" />
                        </div>
                    </div>
                </div>

                <p v-else-if="list.failed.value && list.items.value.length === 0" class="text-muted-foreground px-4 py-10 text-center text-sm">
                    {{ t('notifications.load_failed') }}
                </p>

                <div v-else-if="list.items.value.length === 0" class="px-6 py-10 text-center">
                    <div class="bg-muted mx-auto flex size-12 items-center justify-center rounded-full">
                        <Bell class="text-muted-foreground size-5" />
                    </div>
                    <p class="mt-3 text-sm font-medium">{{ t('notifications.empty_title') }}</p>
                    <p class="text-muted-foreground mt-1 text-xs">{{ t('notifications.empty_body') }}</p>
                </div>

                <div v-else class="divide-y">
                    <NotificationItem
                        v-for="notification in list.items.value"
                        :key="notification.id"
                        :notification="notification"
                        @open="openNotification"
                    />
                </div>
            </ScrollArea>

            <div class="border-t">
                <Link
                    :href="notifications.index.url()"
                    class="text-primary hover:bg-muted/60 block px-4 py-2.5 text-center text-xs font-medium transition-colors"
                    @click="open = false"
                >
                    {{ t('notifications.view_all') }}
                </Link>
            </div>
        </PopoverContent>
    </Popover>
</template>
