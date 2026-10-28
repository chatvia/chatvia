<script setup lang="ts">
/**
 * Setup → Languages: which UI languages the agent supports. At least one must
 * stay on; selecting all is stored as "all" so new platform languages follow.
 */
import SetupSaveFooter from '@/dashboard/agents/components/setup/SetupSaveFooter.vue';
import SetupSectionHeading from '@/dashboard/agents/components/setup/SetupSectionHeading.vue';
import { Button } from '@/shared/ui/button';
import { Checkbox } from '@/shared/ui/checkbox';
import { useSetupContext } from '@/dashboard/agents/composables/setup/useSetupContext';
import { useI18n } from 'vue-i18n';

defineProps<{
    // code → name of every platform language.
    availableLanguages: Record<string, string>;
}>();

const { t } = useI18n();
const { locked, languages } = useSetupContext();
const { allCodes, form, isSaving, canSave, toggle, selectAll, clear, save } = languages;
</script>

<template>
    <div class="max-w-3xl space-y-5 p-6">
        <SetupSectionHeading :title="t('agents.setup.languages.title')" :description="t('agents.setup.languages.description')" />

        <div class="flex items-center gap-2">
            <Button type="button" variant="outline" size="sm" :disabled="locked" @click="selectAll">
                {{ t('agents.setup.languages.select_all') }}
            </Button>
            <Button type="button" variant="outline" size="sm" :disabled="locked" @click="clear">
                {{ t('agents.setup.languages.clear_all') }}
            </Button>
            <span class="text-muted-foreground ms-auto text-xs">
                {{ t('agents.setup.languages.count', { count: form.enabled_languages.length, total: allCodes.length }) }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-x-6 gap-y-1 rounded-md border p-3 sm:grid-cols-3">
            <label
                v-for="code in allCodes"
                :key="code"
                class="hover:bg-muted/50 flex cursor-pointer items-center gap-2 rounded px-1.5 py-1.5 text-sm"
            >
                <Checkbox
                    :model-value="form.enabled_languages.includes(code)"
                    :disabled="locked"
                    @update:model-value="(value: boolean | 'indeterminate') => toggle(code, value === true)"
                />
                <span class="truncate">{{ availableLanguages[code] }}</span>
            </label>
        </div>

        <p v-if="!canSave" class="text-destructive text-xs">
            {{ t('agents.setup.languages.min_required') }}
        </p>
        <p class="text-muted-foreground text-xs">{{ t('agents.setup.languages.hint') }}</p>

        <SetupSaveFooter divided :saving="isSaving" :disabled="!canSave" @save="save" />
    </div>
</template>
