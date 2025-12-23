<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { FileText } from 'lucide-vue-next';
import { computed } from 'vue';

interface Source {
    content: string;
    metadata?: {
        title?: string;
        file_name?: string;
        page?: number;
        chunk_index?: number;
        [key: string]: any;
    };
}

const props = defineProps<{
    sources: Source[];
}>();

const sourcesCount = computed(() => props.sources?.length || 0);

const formatSourceTitle = (source: Source, index: number) => {
    if (source.metadata?.title) {
        return source.metadata.title;
    }
    if (source.metadata?.file_name) {
        const fileName = source.metadata.file_name;
        const page = source.metadata.page;
        return page ? `${fileName} (Page ${page})` : fileName;
    }
    return `Source ${index + 1}`;
};

const formatSourceSubtitle = (source: Source) => {
    const parts = [];
    if (source.metadata?.chunk_index !== undefined) {
        parts.push(`Chunk ${source.metadata.chunk_index}`);
    }
    if (source.metadata?.page !== undefined) {
        parts.push(`Page ${source.metadata.page}`);
    }
    return parts.length > 0 ? parts.join(' • ') : null;
};
</script>

<template>
    <Dialog>
        <DialogTrigger asChild>
            <Button variant="outline" size="sm" class="h-7 px-2 text-xs" :disabled="sourcesCount === 0">
                <FileText class="mr-1 h-3 w-3" />
                {{ sourcesCount }} Source{{ sourcesCount !== 1 ? 's' : '' }}
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[80vh] max-w-4xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <FileText class="h-5 w-5" />
                    Knowledge Sources
                </DialogTitle>
                <DialogDescription> {{ sourcesCount }} source{{ sourcesCount !== 1 ? 's' : '' }} used to generate this response </DialogDescription>
            </DialogHeader>

            <ScrollArea class="max-h-[60vh] pr-4">
                <div class="space-y-4">
                    <div v-for="(source, index) in sources" :key="index" class="space-y-3 rounded-lg border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h4 class="truncate text-sm font-medium">
                                    {{ formatSourceTitle(source, index) }}
                                </h4>
                                <p v-if="formatSourceSubtitle(source)" class="text-muted-foreground mt-1 text-xs">
                                    {{ formatSourceSubtitle(source) }}
                                </p>
                            </div>
                            <Badge variant="secondary" class="text-xs">
                                {{ index + 1 }}
                            </Badge>
                        </div>

                        <div class="text-muted-foreground bg-muted/30 border-primary/20 rounded border-l-2 p-3 text-sm">
                            <p class="whitespace-pre-wrap">{{ source.content }}</p>
                        </div>

                        <div v-if="source.metadata" class="flex flex-wrap gap-1">
                            <Badge v-if="source.metadata.file_name" variant="outline" class="text-xs">
                                {{ source.metadata.file_name }}
                            </Badge>
                            <Badge v-if="source.metadata.page" variant="outline" class="text-xs"> Page {{ source.metadata.page }} </Badge>
                        </div>
                    </div>
                </div>
            </ScrollArea>
        </DialogContent>
    </Dialog>
</template>
