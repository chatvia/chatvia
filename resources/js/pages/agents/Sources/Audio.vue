<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import AgentLayout from '@/layouts/AgentLayout.vue';
import Files from './Files.vue';
import VoiceRecorder from '@/components/VoiceRecorder.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Upload, Mic } from 'lucide-vue-next';
import axios from 'axios';
import { toast } from 'vue-sonner';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
}

interface UsageData {
    minutes?: {
        limit: number;
        used: number;
        remaining: number;
    };
}

const props = defineProps<Props>();
const filesRef = ref<InstanceType<typeof Files> | null>(null);
const activeTab = ref('upload');
const isUploading = ref(false);
const usageData = ref<UsageData>({});

// Fetch usage data to get available minutes
const fetchUsageData = async () => {
    try {
        const response = await axios.get(route('usage'));
        usageData.value = response.data;
    } catch (error) {
        console.error('Error fetching usage data:', error);
    }
};

// Max recording duration in seconds based on available minutes
const maxRecordingDuration = computed(() => {
    const remainingMinutes = usageData.value.minutes?.remaining ?? 10;
    // Convert to seconds, cap at 60 minutes max
    return Math.min(remainingMinutes * 60, 3600);
});

onMounted(() => {
    fetchUsageData();
});

const handleRecorded = async (data: { blob: Blob; name: string; duration: number }) => {
    isUploading.value = true;
    
    try {
        // Create a File object from the Blob
        const file = new File([data.blob], data.name, { type: data.blob.type });
        
        // Upload the file using FormData
        const formData = new FormData();
        formData.append('file', file);
        
        await axios.post(route('agents.sources.files.upload', { agent: props.agent.id }), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        
        toast.success('Recording uploaded successfully!', {
            description: `"${data.name}" has been added to your audio sources.`,
        });
        
        // Refresh the files list and usage data
        if (filesRef.value) {
            filesRef.value.refreshFiles?.();
        }
        await fetchUsageData();
        
        // Switch to upload tab to see the file
        activeTab.value = 'upload';
    } catch (error: any) {
        console.error('Error uploading recording:', error);
        const errorMessage = error.response?.data?.message || error.message || 'Failed to upload recording';
        toast.error('Upload failed', {
            description: errorMessage,
        });
    } finally {
        isUploading.value = false;
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Audio">
        <div class="flex justify-center">
            <div class="w-full max-w-6xl space-y-6">
                <Tabs v-model="activeTab" class="w-full">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="upload" class="flex items-center gap-2">
                            <Upload class="h-4 w-4" />
                            Upload Files
                        </TabsTrigger>
                        <TabsTrigger value="record" class="flex items-center gap-2">
                            <Mic class="h-4 w-4" />
                            Record Voice
                        </TabsTrigger>
                    </TabsList>
                    
                    <TabsContent value="upload" class="mt-6">
                        <Files ref="filesRef" :agent="agent" file-type="audio" />
                    </TabsContent>
                    
                    <TabsContent value="record" class="mt-6">
                        <VoiceRecorder
                            title="Record Voice Note"
                            description="Record audio directly from your microphone to add to your agent's knowledge base"
                            :max-duration="maxRecordingDuration"
                            :disabled="isUploading || maxRecordingDuration <= 0"
                            @recorded="handleRecorded"
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    </AgentLayout>
</template>

