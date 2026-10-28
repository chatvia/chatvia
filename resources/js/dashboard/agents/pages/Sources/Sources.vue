<script setup lang="ts">
/**
 * The agent's Knowledge Sources page (`agents.sources`). It shows the source
 * screen that matches the current URL (web, text, Q&A, …) and falls back to
 * the files screen, under the agent's "Search knowledge" switch.
 */
import KnowledgeSearchSwitch from '@/dashboard/agents/components/sources/KnowledgeSearchSwitch.vue';
import AgentLayout from '@/dashboard/agents/layouts/AgentLayout.vue';
import { usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';
import Files from './Files.vue';
import QaComponent from './QaComponent.vue';
import TextComponent from './TextComponent.vue';
import WebComponent from './WebComponent.vue';

type ContentType = 'web' | 'text' | 'pdf' | 'word' | 'markdown' | 'audio' | 'video' | 'qa';

defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    knowledgeSearch: Domain.Knowledge.Retrieval.Data.KnowledgeSearchStateData;
}>();

const page = usePage();
const { t } = useI18n();

/** URL part → content type, checked in this order; the first match wins. */
const URL_CONTENT_TYPES: [string, ContentType][] = [
    ['/sources/web', 'web'],
    ['/sources/text', 'text'],
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
    qa: QaComponent,
};

/** Content type → the i18n key of its page description. */
const DESCRIPTION_KEYS: Record<ContentType, string> = {
    pdf: 'agents.sources.page_description.files',
    word: 'agents.sources.page_description.files',
    markdown: 'agents.sources.page_description.markdown',
    audio: 'agents.sources.page_description.audio',
    video: 'agents.sources.page_description.video',
    web: 'agents.sources.page_description.web',
    text: 'agents.sources.page_description.text',
    qa: 'agents.sources.page_description.qa',
};

function contentTypeFromUrl(url: string): ContentType {
    const match = URL_CONTENT_TYPES.find(([urlPart]) => url.includes(urlPart));

    return match?.[1] ?? 'pdf';
}

const selectedContentType = contentTypeFromUrl(page.url);
const currentComponent = SCREENS[selectedContentType];
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.sources')" :description="t(DESCRIPTION_KEYS[selectedContentType])">
        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-6xl">
                <KnowledgeSearchSwitch :agent-id="agent.external_id" :state="knowledgeSearch" />
                <component :is="currentComponent" :agent="agent" :file-type="selectedContentType" />
            </div>
        </div>
    </AgentLayout>
</template>
