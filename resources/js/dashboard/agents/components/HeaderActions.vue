<template>
    <div class="flex items-center gap-2">
        <!-- Success ChatMessage -->
        <FormSuccessMessage v-if="showSuccessMessage" :show="showSuccessMessage" :message="displaySuccessMessage" class="me-2" />

        <!-- Error ChatMessage -->
        <div v-if="showErrorMessage && !showSuccessMessage" class="text-destructive me-2 text-xs">
            {{ displayErrorMessage }}
        </div>

        <!-- Secondary Actions (like Restore Defaults) -->
        <template v-for="action in secondaryActions" :key="action.id">
            <Button type="button" variant="outline" size="sm" @click="action.onClick" :disabled="action.disabled" class="gap-2">
                <component :is="action.icon" v-if="action.icon" class="h-4 w-4" />
                <span class="hidden sm:inline">{{ action.label }}</span>
            </Button>
        </template>

        <!-- Primary Save Button with Pulse Badge -->
        <div class="relative">
            <Button :disabled="isSubmitting" size="sm" type="submit" :form="formId" class="min-w-[100px] gap-2">
                <component :is="saveIcon" v-if="!isSubmitting && saveIcon" class="h-4 w-4" />
                <div v-else-if="isSubmitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                {{ isSubmitting ? t('common.saving') : displaySaveLabel }}
            </Button>

            <!-- Pulse Badge Circle -->
            <div v-if="hasUnsavedChanges && !isSubmitting" class="absolute -end-1 -top-1 h-3 w-3 animate-pulse rounded-full bg-orange-500 shadow-lg">
                <div class="absolute inset-0 h-3 w-3 animate-ping rounded-full bg-orange-500 opacity-75"></div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import FormSuccessMessage from '@/dashboard/agents/components/FormSuccessMessage.vue';
import { Button } from '@/shared/ui/button';
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

/*
 * Page-header action bar for settings forms: secondary buttons, a submit button
 * bound to a form by id, an "unsaved changes" dot and inline save feedback.
 */

interface SecondaryAction {
    id: string;
    label: string;
    icon?: Component;
    onClick: () => void;
    disabled?: boolean;
}

interface Props {
    // Save button props
    isSubmitting?: boolean;
    hasUnsavedChanges?: boolean;
    saveLabel?: string;
    saveIcon?: Component;
    formId?: string;

    // ChatMessage props
    showSuccessMessage?: boolean;
    successMessage?: string;
    showErrorMessage?: boolean;
    errorMessage?: string;

    // Secondary actions
    secondaryActions?: SecondaryAction[];
}

const props = withDefaults(defineProps<Props>(), {
    isSubmitting: false,
    hasUnsavedChanges: false,
    saveIcon: undefined,
    formId: undefined,
    showSuccessMessage: false,
    showErrorMessage: false,
    secondaryActions: () => [],
});

const { t } = useI18n();

const displaySaveLabel = computed(() => props.saveLabel ?? t('common.save'));
const displaySuccessMessage = computed(() => props.successMessage ?? t('shared.settings_saved_successfully'));
const displayErrorMessage = computed(() => props.errorMessage ?? t('shared.fix_errors_below'));
</script>
