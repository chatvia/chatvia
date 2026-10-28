<script setup lang="ts">
// One notification row: an icon by kind, the sentence, the agent and how long
// ago, and a dot while unread. Clicking it opens what it links to; the
// Notifications page also offers "Mark as read" on hover.
import { Button } from '@/shared/ui/button';
import { notificationLook, useNotificationText } from '@/dashboard/composables/notifications/useNotificationText';
import type { UserNotification } from '@/shared/composables/notifications/notificationStore';
import { useTimeAgo } from '@/dashboard/composables/useTimeAgo';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    notification: UserNotification;
    // The page shows a "Mark as read" button; the bell's popover does not.
    showMarkRead?: boolean;
}>();

const emit = defineEmits<{
    open: [notification: UserNotification];
    markRead: [notification: UserNotification];
}>();

const { t } = useI18n();
const { describe } = useNotificationText();
const { formatTimeAgo } = useTimeAgo();

const text = computed(() => describe(props.notification));
const look = computed(() => notificationLook(props.notification.kind));
</script>

<template>
    <div
        role="button"
        tabindex="0"
        class="group hover:bg-muted/60 focus-visible:bg-muted/60 relative flex cursor-pointer gap-3 px-4 py-3 transition-colors outline-none"
        :class="notification.read ? '' : 'bg-primary/[0.03]'"
        @click="emit('open', notification)"
        @keydown.enter.prevent="emit('open', notification)"
    >
        <div class="flex size-9 shrink-0 items-center justify-center rounded-full" :class="look.tone">
            <component :is="look.icon" class="size-4" />
        </div>

        <div class="min-w-0 flex-1">
            <p class="line-clamp-2 text-sm leading-snug" :class="notification.read ? 'text-foreground/80' : 'text-foreground font-medium'">
                {{ text.title }}
            </p>
            <p v-if="text.body" class="text-muted-foreground mt-0.5 line-clamp-2 text-xs leading-snug">{{ text.body }}</p>
            <p class="text-muted-foreground mt-1 flex items-center gap-1.5 text-[11px]">
                <span v-if="notification.content.agent" class="max-w-40 truncate">{{ notification.content.agent.name }}</span>
                <span v-if="notification.content.agent" aria-hidden="true">·</span>
                <time :datetime="notification.updated_at">{{ formatTimeAgo(notification.updated_at) }}</time>
            </p>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-2">
            <span v-if="!notification.read" class="bg-primary mt-1.5 size-2 rounded-full" :aria-label="t('notifications.unread')" />
            <Button
                v-if="showMarkRead && !notification.read"
                variant="ghost"
                size="icon"
                class="size-7 opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                :aria-label="t('notifications.mark_read')"
                @click.stop="emit('markRead', notification)"
            >
                <Check class="size-3.5" />
            </Button>
        </div>
    </div>
</template>
