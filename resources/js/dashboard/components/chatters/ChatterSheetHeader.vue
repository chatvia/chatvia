<script setup lang="ts">
// Header of a chatter detail sheet: avatar, name, status pill, email, the
// copyable chatter ID and the join date. Both chatter sheets (agent tab and
// workspace page) render it; each passes its own translated labels.
import ChatterStatusBadge from '@/dashboard/components/chatters/ChatterStatusBadge.vue';
import type { ChatterSheetHeaderLabels, ChatterSummary } from '@/dashboard/components/chatters/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/shared/ui/avatar';
import { Button } from '@/shared/ui/button';
import { SheetDescription, SheetHeader, SheetTitle } from '@/shared/ui/sheet';
import { useClipboard } from '@vueuse/core';
import { Check, Copy } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    chatter: ChatterSummary;
    labels: ChatterSheetHeaderLabels;
}>();

const { copy, copied } = useClipboard({ legacy: true, copiedDuring: 2000 });

// Up-to-two-letter avatar initials from the chatter's name.
const initials = computed(() => {
    const name = props.chatter.name?.trim();
    if (!name) return '?';
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
});
</script>

<template>
    <SheetHeader class="space-y-4 border-b px-6 pt-7 pb-6">
        <div class="flex items-start gap-4">
            <Avatar class="size-14 shrink-0">
                <AvatarImage v-if="chatter.avatar" :src="chatter.avatar" :alt="chatter.name" />
                <AvatarFallback class="text-base font-semibold">{{ initials }}</AvatarFallback>
            </Avatar>
            <div class="min-w-0 flex-1 pt-1">
                <SheetTitle class="truncate text-lg">{{ chatter.name }}</SheetTitle>
                <div class="mt-1.5 flex items-center gap-2 text-sm">
                    <ChatterStatusBadge :status="chatter.status" :pending-label="labels.pending" :active-label="labels.active" />
                    <span class="text-muted-foreground truncate">{{ chatter.email || labels.noEmail }}</span>
                </div>
            </div>
        </div>
        <SheetDescription class="sr-only">{{ labels.description }}</SheetDescription>
        <div class="space-y-1.5">
            <div class="flex items-center gap-1.5 text-xs">
                <span class="text-muted-foreground">{{ labels.id }}</span>
                <code class="min-w-0 flex-1 truncate font-mono" :title="chatter.external_id">{{ chatter.external_id }}</code>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-6 shrink-0 px-1.5"
                    :title="labels.copyId"
                    :aria-label="labels.copyId"
                    @click="copy(chatter.external_id)"
                >
                    <Check v-if="copied" class="size-3.5 text-green-600" />
                    <Copy v-else class="size-3.5" />
                </Button>
            </div>
            <p class="text-muted-foreground text-xs">{{ labels.joined }}</p>
        </div>
    </SheetHeader>
</template>
