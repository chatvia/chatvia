<script setup lang="ts">
import { ref } from 'vue';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { FileText, Globe } from 'lucide-vue-next';
import KnowledgeFiles from './KnowledgeBase/KnowledgeFiles.vue';
import Web from './KnowledgeBase/Web.vue';

interface Props {
    agent: any
}

const props = defineProps<Props>();

// Default to 'files' tab
const activeTab = ref('files');
</script>

<template>
    <AgentLayout :agent="agent" title="Knowledge Base">
        <div class="space-y-6">
            <div class="flex flex-col space-y-4">
                <div>
                    <h1 class="text-2xl font-semibold">Knowledge Base</h1>
                    <p class="text-muted-foreground">
                        Manage your agent's knowledge sources including documents and web content.
                    </p>
                </div>

                <Tabs v-model="activeTab" default-value="files" class="w-full">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="files" class="flex items-center gap-2">
                            <FileText class="h-4 w-4" />
                            Files
                        </TabsTrigger>
                        <TabsTrigger value="web" class="flex items-center gap-2">
                            <Globe class="h-4 w-4" />
                            Web
                        </TabsTrigger>
                    </TabsList>
                    
                    <TabsContent value="files" class="mt-6">
                        <KnowledgeFiles :agent="agent" />
                    </TabsContent>
                    
                    <TabsContent value="web" class="mt-6">
                        <Web :agent="agent" />
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    </AgentLayout>
</template>
