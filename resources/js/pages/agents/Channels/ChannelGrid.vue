<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Settings2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import type { ChannelItem } from './types';

defineProps<{ items: ChannelItem[] }>();

const emit = defineEmits<{ open: [item: ChannelItem] }>();

const { t } = useI18n();
</script>

<template>
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
                    <Button variant="outline" class="mt-4 w-full" @click="emit('open', item)">
                        <Settings2 class="me-2 size-4" />
                        {{ t('agents.channels.setup_tab') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
