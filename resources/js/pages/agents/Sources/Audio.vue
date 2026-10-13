<script setup lang="ts">
/**
 * Audio sources of an agent: upload audio files (the shared files page,
 * limited to audio) or record a voice note in the browser. A recording may
 * last at most one hour. While the admin has audio turned off, only the list
 * of existing audio files shows.
 */
import { uploadSourceFile } from '@/api/fileSources';
import VoiceRecorder from '@/components/VoiceRecorder.vue';
import { useKnowledgeFileTypes } from '@/composables/agents/sources/files/useKnowledgeFileTypes';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { failureReason } from '@/lib/failureReason';
import { Mic, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import Files from './Files.vue';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}>();

const MAX_RECORDING_SECONDS = 3600;

const { t } = useI18n();
const acceptsAudio = useKnowledgeFileTypes().isAccepted('audio');
const filesRef = ref<InstanceType<typeof Files> | null>(null);
const activeTab = ref('upload');
const isUploading = ref(false);

const handleRecorded = async (recording: { blob: Blob; name: string; duration: number }) => {
    isUploading.value = true;

    try {
        await uploadSourceFile(props.agent.external_id, new File([recording.blob], recording.name, { type: recording.blob.type }));

        toast.success(t('agents.sources.audio.recording_uploaded'), {
            description: t('agents.sources.audio.recording_uploaded_description', { name: recording.name }),
        });

        filesRef.value?.refreshFiles?.();

        // Show the uploads tab, where the new file waits for training.
        activeTab.value = 'upload';
    } catch (error) {
        toast.error(t('agents.sources.audio.upload_failed'), {
            description: failureReason(error) || t('agents.sources.audio.upload_failed_fallback'),
        });
    } finally {
        isUploading.value = false;
    }
};
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.audio')">
        <div class="flex justify-center">
            <div class="w-full max-w-6xl space-y-6">
                <Files v-if="!acceptsAudio" :agent="agent" file-type="audio" />

                <Tabs v-else v-model="activeTab" class="w-full">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="upload" class="flex items-center gap-2">
                            <Upload class="h-4 w-4" />
                            {{ t('agents.sources.audio.upload_files') }}
                        </TabsTrigger>
                        <TabsTrigger value="record" class="flex items-center gap-2">
                            <Mic class="h-4 w-4" />
                            {{ t('agents.sources.audio.record_voice') }}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="upload" class="mt-6">
                        <Files ref="filesRef" :agent="agent" file-type="audio" />
                    </TabsContent>

                    <TabsContent value="record" class="mt-6">
                        <VoiceRecorder
                            :title="t('agents.sources.audio.record_voice_note')"
                            :description="t('agents.sources.audio.record_description')"
                            :max-duration="MAX_RECORDING_SECONDS"
                            :disabled="isUploading"
                            @recorded="handleRecorded"
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    </AgentLayout>
</template>
