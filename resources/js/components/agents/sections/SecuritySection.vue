<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { Shield } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        isPublic?: boolean;
        sites?: string[];
        showIsPublic?: boolean;
        showSites?: boolean;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    { isPublic: false, sites: () => [], showIsPublic: true, showSites: false, errors: () => ({}), disabled: false },
);

defineEmits<{
    'update:isPublic': [value: boolean];
    'update:sites': [value: string[]];
}>();
</script>

<template>
    <div class="space-y-6">
        <template v-if="showIsPublic">
            <div>
                <h3 class="text-sm font-medium">Access Control</h3>
                <p class="text-muted-foreground mt-1 text-xs">
                    A protected agent only loads for visitors you authenticate with a signed token.
                </p>
            </div>

            <div class="flex items-center justify-between rounded-lg border p-4">
                <div class="space-y-0.5 pr-4">
                    <Label for="security-public">Make public</Label>
                    <p class="text-muted-foreground text-xs">
                        When public, anyone can load the widget. When off, the widget requires a signed
                        <code class="bg-muted rounded px-1 py-0.5 text-xs">data-auth</code> token to load.
                    </p>
                </div>
                <Switch
                    id="security-public"
                    :model-value="isPublic"
                    :disabled="disabled"
                    @update:model-value="$emit('update:isPublic', Boolean($event))"
                />
            </div>
            <p v-if="errors?.is_public" class="text-destructive text-sm">{{ errors.is_public }}</p>

            <Alert v-if="!isPublic">
                <Shield class="h-4 w-4" />
                <AlertTitle>Protected</AlertTitle>
                <AlertDescription>
                    Requires a signed <code class="bg-muted rounded px-1 py-0.5 text-xs">data-auth</code> JWT to load.
                </AlertDescription>
            </Alert>
        </template>

        <div v-if="showSites" class="space-y-2">
            <Label>Allowed websites</Label>
            <p class="text-muted-foreground text-xs">Domains where this widget is allowed to load (without https://). Press Enter to add.</p>
            <TagsInput
                :model-value="sites"
                :disabled="disabled"
                class="w-full"
                @update:model-value="$emit('update:sites', ($event as string[]) ?? [])"
            >
                <TagsInputItem v-for="(site, index) in sites" :key="index" :value="site">
                    <TagsInputItemText />
                    <TagsInputItemDelete />
                </TagsInputItem>
                <TagsInputInput placeholder="e.g., example.com" />
            </TagsInput>
            <p v-if="errors?.widget_sites" class="text-destructive text-sm">{{ errors.widget_sites }}</p>
            <p v-else-if="errors?.sites" class="text-destructive text-sm">{{ errors.sites }}</p>
        </div>
    </div>
</template>
