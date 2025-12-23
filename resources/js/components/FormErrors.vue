<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    errors: Record<string, string>;
    title?: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Error',
    class: '',
});

const hasErrors = computed(() => Object.keys(props.errors).length > 0);
</script>

<template>
    <div v-if="hasErrors" :class="class">
        <Alert variant="destructive" class="bg-red-50 text-red-900 dark:bg-red-900/20 dark:text-red-300">
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>{{ title }}</AlertTitle>
            <AlertDescription>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="(error, key) in errors" :key="key">
                        {{ error }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>
    </div>
</template>
