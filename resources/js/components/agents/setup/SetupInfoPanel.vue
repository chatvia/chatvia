<script setup lang="ts">
/** Setup → Info: the chat title, welcome message and composer placeholder, per language. */
import AppearanceTextEditor from '@/components/agents/AppearanceTextEditor.vue';
import SetupSaveFooter from '@/components/agents/setup/SetupSaveFooter.vue';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';

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
