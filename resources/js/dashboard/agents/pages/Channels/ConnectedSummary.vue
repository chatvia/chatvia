<script setup lang="ts">
// The green "Connected" row at the top of a connected channel; the default slot
// names the connected account (bot username, workspace, page, number, …).
import { Check } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <div class="bg-muted/50 flex items-center gap-3 rounded-lg border p-4">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10">
            <Check class="size-5 text-emerald-600" />
        </div>
        <div class="min-w-0">
            <p class="text-sm font-medium">{{ t('agents.channels.connected') }}</p>
            <p class="text-muted-foreground truncate text-sm"><slot /></p>
        </div>
    </div>
</template>
