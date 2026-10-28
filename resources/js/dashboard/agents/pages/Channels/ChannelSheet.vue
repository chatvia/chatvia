<script setup lang="ts">
// The slide-over that holds one channel's setup: the logo/name/tagline header
// plus the channel panel passed in the default slot. The sheet unmounts its
// content when it closes, so each open starts the panel with a fresh form.
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/shared/ui/sheet';
import type { ChannelItem } from './types';

defineProps<{ channel: ChannelItem | null }>();

const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="w-full overflow-y-auto p-0 sm:max-w-md">
            <template v-if="channel">
                <SheetHeader class="border-b p-6">
                    <div class="mb-1 flex items-center gap-3">
                        <img :src="channel.logo" :alt="channel.name" class="size-9" />
                        <SheetTitle>{{ channel.name }}</SheetTitle>
                    </div>
                    <SheetDescription>{{ channel.tagline }}</SheetDescription>
                </SheetHeader>

                <div class="space-y-5 p-6">
                    <slot />
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>
