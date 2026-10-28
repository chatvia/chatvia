<script setup lang="ts">
/** Setup → Info: the chat title, welcome message and composer placeholder, per language. */
import AppearanceTextEditor from '@/dashboard/agents/components/AppearanceTextEditor.vue';
import SetupSaveFooter from '@/dashboard/agents/components/setup/SetupSaveFooter.vue';
import { useSetupContext } from '@/dashboard/agents/composables/setup/useSetupContext';

const { locked, text, languages } = useSetupContext();
const { form, translations, overrides, isSaving, save } = text;
const { enabledLanguageMap } = languages;
</script>

<template>
    <div class="space-y-6 p-6">
        <form @submit.prevent="save" class="space-y-6">
            <AppearanceTextEditor
                v-model:welcome-message="form.welcome_message"
                v-model:chat-window-title="form.chat_window_title"
                v-model:composer-placeholder="form.composer_placeholder"
                v-model:notice="form.notice"
                v-model:overrides="overrides"
                :translations="translations"
                :languages="enabledLanguageMap"
                :errors="form.errors as Record<string, string>"
                :disabled="locked"
            />

            <SetupSaveFooter submit divided :saving="isSaving" />
        </form>
    </div>
</template>
