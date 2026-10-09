<script setup lang="ts">
// Teaser shown instead of the security settings to workspaces whose plan does
// not include the Security & Compliance suite.
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { Globe, Lock, Network, Sparkles } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <Card>
        <CardContent class="flex flex-col items-center gap-4 py-12 text-center">
            <div class="bg-primary/10 flex size-12 items-center justify-center rounded-full">
                <Sparkles class="text-primary size-6" />
            </div>
            <div class="space-y-1">
                <h2 class="text-lg font-semibold">{{ t('workspace.security.teaser.title') }}</h2>
                <p class="text-muted-foreground mx-auto max-w-md text-sm">{{ t('workspace.security.teaser.description') }}</p>
            </div>
            <ul class="text-muted-foreground mx-auto grid max-w-md gap-2 text-start text-sm">
                <li class="flex items-center gap-2"><Network class="size-4 shrink-0" /> {{ t('workspace.security.teaser.ip') }}</li>
                <li class="flex items-center gap-2"><Globe class="size-4 shrink-0" /> {{ t('workspace.security.teaser.geo') }}</li>
                <li class="flex items-center gap-2"><Lock class="size-4 shrink-0" /> {{ t('workspace.security.teaser.more') }}</li>
            </ul>
            <Button as-child>
                <Link :href="route('billing.packages')">{{ t('workspace.security.teaser.cta') }}</Link>
            </Button>
        </CardContent>
    </Card>
</template>
