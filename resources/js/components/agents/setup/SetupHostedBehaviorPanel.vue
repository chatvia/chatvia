<script setup lang="ts">
/** Setup → Behavior on the Hosted Chat tab: theme, language and page options of the hosted chat. */
import SetupIconSelect from '@/components/agents/setup/SetupIconSelect.vue';
import SetupSaveFooter from '@/components/agents/setup/SetupSaveFooter.vue';
import SetupSectionHeading from '@/components/agents/setup/SetupSectionHeading.vue';
import SetupSwitchRow from '@/components/agents/setup/SetupSwitchRow.vue';
import { languageOptions, useBehaviorOptions } from '@/composables/agents/setup/useSetupBehavior';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';
import { Languages, Palette } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    // code → name of every platform language (plus 'auto').
    availableLanguages: Record<string, string>;
}>();

const { t } = useI18n();
const { locked, behavior } = useSetupContext();
const { hostedForm: form, isSavingHosted: isSaving, saveHosted: save } = behavior;
const { themeOptions } = useBehaviorOptions();
const languageChoices = computed(() => languageOptions(props.availableLanguages));
</script>

<template>
    <div class="space-y-6 p-6">
        <SetupSectionHeading :title="t('agents.setup.behavior.hosted_title')" :description="t('agents.setup.behavior.hosted_description')" />
        <div class="max-w-3xl space-y-5">
            <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                <SetupIconSelect
                    id="hosted-default-theme"
                    v-model="form.default_theme"
                    :label="t('agents.setup.behavior.default_theme')"
                    :icon="Palette"
                    :options="themeOptions"
                    :disabled="locked"
                />
                <SetupIconSelect
                    id="hosted-language"
                    v-model="form.language"
                    :label="t('agents.setup.behavior.language')"
                    :icon="Languages"
                    :options="languageChoices"
                    :disabled="locked"
                />
            </div>
            <div class="divide-y rounded-md border">
                <SetupSwitchRow
                    id="hosted-remove-sidebar"
                    v-model="form.remove_sidebar"
                    :label="t('agents.setup.behavior.remove_sidebar')"
                    :disabled="locked"
                />
                <SetupSwitchRow
                    id="hosted-hide-sidebar"
                    v-model="form.hide_sidebar_on_page_load"
                    :label="t('agents.setup.behavior.hide_sidebar')"
                    :disabled="locked"
                />
                <SetupSwitchRow
                    id="hosted-theme-switcher"
                    v-model="form.enable_theme_switcher"
                    :label="t('agents.setup.behavior.theme_switcher')"
                    :disabled="locked"
                />
                <SetupSwitchRow
                    id="hosted-chat-expand"
                    v-model="form.enable_chat_expand"
                    :label="t('agents.setup.behavior.chat_expand')"
                    :disabled="locked"
                />
                <SetupSwitchRow
                    id="hosted-language-switcher"
                    v-model="form.show_language_switcher"
                    :label="t('agents.setup.behavior.language_switcher')"
                    :disabled="locked"
                />
            </div>
        </div>
        <SetupSaveFooter divided :saving="isSaving" @save="save" />
    </div>
</template>
