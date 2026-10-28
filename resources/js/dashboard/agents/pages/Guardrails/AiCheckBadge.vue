<script setup lang="ts">
// Marks a guard that asks an AI model, so the owner can see which settings add
// AI calls to a reply (the intro on the page explains how many).
import { Badge } from '@/shared/ui/badge';
import { Sparkles } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <Badge variant="outline" class="text-muted-foreground pointer-events-none gap-1 font-normal">
        <Sparkles class="h-3 w-3" />
        {{ t('agents.guardrails.ai_check') }}
    </Badge>
</template>
