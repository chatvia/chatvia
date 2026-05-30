<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ExternalLink, Globe, MessageSquare, Monitor, Search, Smartphone, Tablet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
    authToken?: string | null;
}

const props = defineProps<Props>();

type Surface = 'hosted_chat' | 'widget' | 'bar';
type Device = 'desktop' | 'tablet' | 'mobile';

const surface = ref<Surface>('hosted_chat');
const device = ref<Device>('desktop');

const hostedChatUrl = computed(() => {
    const base = props.agent.chat_url;
    if (!props.authToken) return base;
    return `${base}${base.includes('?') ? '&' : '?'}auth_token=${props.authToken}`;
});

// Widget renders as a floating bubble on a blank host page. `full-preview=true`
// tells the preview view NOT to mount the widget into a chat container — the
// embed script positions itself as it would on a real customer site.
const widgetUrl = computed(() => `${route('agents.widget.preview', { agent: props.agent.external_id })}?full-preview=true`);

const barUrl = computed(() => route('agents.bar.preview', { agent: props.agent.external_id }));

const currentUrl = computed(() => {
    if (surface.value === 'hosted_chat') return hostedChatUrl.value;
    if (surface.value === 'widget') return widgetUrl.value;
    return barUrl.value;
});

// Device frames match the ones in PreviewModal so the experience is consistent
// across the dashboard. Desktop fills the available area; tablet and mobile are
// fixed-width "device frames" so the admin sees how the surface behaves at
// real breakpoints.
const frameClass = computed(() => {
    if (device.value === 'tablet') {
        return 'h-[calc(100%-2rem)] max-h-[900px] w-[768px] rounded-xl border shadow-lg overflow-hidden bg-white';
    }
    if (device.value === 'mobile') {
        return 'h-[calc(100%-2rem)] max-h-[800px] w-[414px] rounded-xl border shadow-lg overflow-hidden bg-white';
    }
    return 'h-full w-full';
});

const openFullPreview = () => {
    if (currentUrl.value) {
        window.open(currentUrl.value, '_blank');
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Playground" :no-padding="true" :full-height="true">
        <div class="bg-background flex h-full flex-col">
            <!-- Surface toggle + device toggle + open-in-new-tab -->
            <div class="border-border flex flex-shrink-0 flex-wrap items-center justify-between gap-3 border-b px-4 py-3 lg:px-6">
                <Tabs v-model="surface">
                    <TabsList>
                        <TabsTrigger value="hosted_chat" class="gap-2">
                            <MessageSquare class="h-4 w-4" />
                            Hosted Chat
                        </TabsTrigger>
                        <TabsTrigger value="widget" class="gap-2">
                            <Globe class="h-4 w-4" />
                            Widget
                        </TabsTrigger>
                        <TabsTrigger value="bar" class="gap-2">
                            <Search class="h-4 w-4" />
                            Bar
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                <div class="flex items-center gap-2">
                    <Tabs v-model="device">
                        <TabsList>
                            <TabsTrigger value="desktop" class="gap-2">
                                <Monitor class="h-4 w-4" />
                                <span class="hidden sm:inline">Desktop</span>
                            </TabsTrigger>
                            <TabsTrigger value="tablet" class="gap-2">
                                <Tablet class="h-4 w-4" />
                                <span class="hidden sm:inline">Tablet</span>
                            </TabsTrigger>
                            <TabsTrigger value="mobile" class="gap-2">
                                <Smartphone class="h-4 w-4" />
                                <span class="hidden sm:inline">Mobile</span>
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <Button variant="outline" size="sm" class="gap-2" @click="openFullPreview">
                        <ExternalLink class="h-4 w-4" />
                        <span class="hidden sm:inline">Open</span>
                    </Button>
                </div>
            </div>

            <!-- Full-page preview frame. Desktop fills the area; tablet/mobile
                 use a fixed-width device frame so breakpoints are visible. -->
            <div class="bg-muted flex flex-1 items-center justify-center overflow-hidden">
                <div :class="frameClass" class="transition-all duration-300">
                    <iframe
                        :key="`${surface}-${device}`"
                        :src="currentUrl"
                        class="h-full w-full border-0"
                        title="Surface preview"
                        loading="lazy"
                    />
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
