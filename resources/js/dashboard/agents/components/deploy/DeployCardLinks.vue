<script setup lang="ts">
/**
 * The footer links of the Widget and Command Bar cards on the Deploy page:
 * open the JavaScript API docs, or go to Setup to change the look.
 */
import { Button } from '@/shared/ui/button';
import { Link } from '@inertiajs/vue3';
import { Settings2, Terminal } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    setupUrl: string;
}>();

const emit = defineEmits<{
    openApiDocs: [];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="flex flex-wrap items-center justify-end gap-2">
        <Button type="button" variant="ghost" size="sm" class="text-muted-foreground gap-2" @click="emit('openApiDocs')">
            <Terminal class="size-4" /> {{ t('agents.deploy.api_docs') }}
        </Button>
        <Button as-child variant="ghost" size="sm" class="text-muted-foreground gap-2">
            <Link :href="setupUrl"><Settings2 class="size-4" /> {{ t('agents.deploy.customize') }}</Link>
        </Button>
    </div>
</template>
