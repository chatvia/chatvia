<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="!h-screen !max-h-none !w-screen !max-w-none !rounded-none !p-0 [&>button]:hidden">
            <div class="relative flex h-full w-full flex-col">
                <!-- Top Controls Bar -->
                <div class="bg-background flex items-center justify-between border-b p-4">
                    <!-- Device Controls -->
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            :variant="selectedDevice === 'desktop' ? 'default' : 'ghost'"
                            size="sm"
                            @click="switchDevice('desktop')"
                            class="gap-2"
                        >
                            <Monitor class="h-4 w-4" />
                            Desktop
                        </Button>
                        <Button
                            type="button"
                            :variant="selectedDevice === 'tablet' ? 'default' : 'ghost'"
                            size="sm"
                            @click="switchDevice('tablet')"
                            class="gap-2"
                        >
                            <Tablet class="h-4 w-4" />
                            Tablet
                        </Button>
                        <Button
                            type="button"
                            :variant="selectedDevice === 'mobile' ? 'default' : 'ghost'"
                            size="sm"
                            @click="switchDevice('mobile')"
                            class="gap-2"
                        >
                            <Smartphone class="h-4 w-4" />
                            Mobile
                        </Button>
                    </div>

                    <!-- Close Button -->
                    <Button type="button" variant="secondary" size="sm" @click="closeModal" class="gap-2">
                        <X class="h-4 w-4" />
                        Close Preview
                    </Button>
                </div>

                <!-- Preview Content -->
                <div class="flex flex-1 items-center justify-center overflow-hidden">
                    <div
                        v-if="previewType === 'widget'"
                        :class="[
                            'relative overflow-hidden transition-all duration-300',
                            selectedDevice === 'desktop'
                                ? 'h-full w-full'
                                : selectedDevice === 'tablet'
                                  ? 'h-[calc(100vh-8rem)] max-h-[800px] w-[768px] rounded-xl border shadow-lg'
                                  : 'h-[calc(100vh-8rem)] max-h-[700px] w-[414px] rounded-xl border shadow-lg',
                        ]"
                    >
                        <iframe :key="iframeKey" :src="previewUrl" class="h-full w-full border-0" title="Widget Preview" loading="lazy"></iframe>
                    </div>

                    <div v-else-if="previewType === 'embed'" class="flex h-full w-full items-center justify-center p-4">
                        <div :class="getEmbedContainerClass">
                            <iframe
                                :key="iframeKey"
                                :src="previewUrl"
                                :title="iframeTitle"
                                frameborder="0"
                                class="h-full w-full rounded-lg border shadow-lg transition-all duration-300"
                            ></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Monitor, Smartphone, Tablet, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    modelValue: boolean;
    previewType: 'widget' | 'embed';
    previewUrl: string;
    iframeTitle?: string;
    iframeOptions?: any;
}

const props = withDefaults(defineProps<Props>(), {
    iframeTitle: 'Preview',
});

const emit = defineEmits(['update:modelValue']);

const selectedDevice = ref('desktop');
const iframeKey = ref(0);

const isOpen = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

// Device classes for embed preview
const _deviceClasses: Record<string, string> = {
    desktop: 'w-full max-w-4xl h-[600px]',
    tablet: 'w-[768px] h-[500px]',
    mobile: 'w-[375px] h-[600px]',
};

const switchDevice = (device: string) => {
    selectedDevice.value = device;
    iframeKey.value++;
};

const closeModal = () => {
    isOpen.value = false;
};

// Simple container class for embed preview - just responsive breakpoints
const getEmbedContainerClass = computed(() => {
    if (selectedDevice.value === 'mobile') {
        return 'w-[375px] h-[600px] flex-shrink-0';
    }

    if (selectedDevice.value === 'tablet') {
        return 'w-[768px] h-[600px] flex-shrink-0';
    }

    // Desktop - full height and width
    return 'w-full h-full';
});
</script>
