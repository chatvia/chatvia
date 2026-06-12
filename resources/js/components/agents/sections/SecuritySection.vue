<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { Shield } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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
                <h3 class="text-sm font-medium">{{ t('agents.setup.sections.security_title') }}</h3>
                <p class="text-muted-foreground mt-1 text-xs">{{ t('agents.setup.sections.security_description') }}</p>
            </div>

            <div class="flex items-center justify-between rounded-lg border p-4">
                <div class="space-y-0.5 pe-4">
                    <Label for="security-public">{{ t('agents.setup.sections.security_public') }}</Label>
                    <p class="text-muted-foreground text-xs">
                        {{ t('agents.setup.sections.security_public_hint') }}
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
                <AlertTitle>{{ t('agents.setup.sections.security_protected_title') }}</AlertTitle>
                <AlertDescription>
                    {{ t('agents.setup.sections.security_protected_description') }}
                </AlertDescription>
            </Alert>
        </template>

        <div v-if="showSites" class="space-y-2">
            <Label>{{ t('agents.setup.sections.security_sites') }}</Label>
            <p class="text-muted-foreground text-xs">{{ t('agents.setup.sections.security_sites_hint') }}</p>
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
                <TagsInputInput :placeholder="t('agents.setup.sections.security_sites_placeholder')" />
            </TagsInput>
            <p v-if="errors?.widget_sites" class="text-destructive text-sm">{{ errors.widget_sites }}</p>
            <p v-else-if="errors?.sites" class="text-destructive text-sm">{{ errors.sites }}</p>
        </div>
    </div>
</template>
