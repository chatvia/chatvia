<script setup lang="ts">
/**
 * The agent's Knowledge Sources page (`agents.sources`). It shows the source
 * screen that matches the current URL (web, text, Q&A, …) and falls back to
 * the files screen. YouTube only counts when the `youtube_sources` feature is on.
 */
import { useFeature } from '@/composables/useFeature';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';
import Files from './Files.vue';
import QaComponent from './QaComponent.vue';
import TextComponent from './TextComponent.vue';
import WebComponent from './WebComponent.vue';
import YoutubeComponent from './YoutubeComponent.vue';

type ContentType = 'web' | 'text' | 'youtube' | 'pdf' | 'word' | 'markdown' | 'audio' | 'video' | 'qa';

defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}>();

const page = usePage();
const { t } = useI18n();
const { hasFeature } = useFeature();

/** URL part → content type, checked in this order; the first match wins. */
const URL_CONTENT_TYPES: [string, ContentType][] = [
    ['/sources/web', 'web'],
    ['/sources/text', 'text'],
    ['/sources/youtube', 'youtube'],
    ['/sources/pdf', 'pdf'],
    ['/sources/word', 'word'],
    ['/sources/markdown', 'markdown'],
    ['/sources/audio', 'audio'],
    ['/sources/video', 'video'],
    ['/sources/qa', 'qa'],
];

const SCREENS: Record<ContentType, Component> = {
    pdf: Files,
    word: Files,
    markdown: Files,
    audio: Files,
    video: Files,
    web: WebComponent,
    text: TextComponent,
    youtube: YoutubeComponent,
    qa: QaComponent,
};

function contentTypeFromUrl(url: string): ContentType {
    const match = URL_CONTENT_TYPES.find(([urlPart, type]) => url.includes(urlPart) && (type !== 'youtube' || hasFeature('youtube_sources')));

    return match?.[1] ?? 'pdf';
}

const selectedContentType = contentTypeFromUrl(page.url);
const currentComponent = SCREENS[selectedContentType];
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.sources')">
        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-6xl">
                <component :is="currentComponent" :agent="agent" :file-type="selectedContentType" />
            </div>
        </div>
    </AgentLayout>
</template>
