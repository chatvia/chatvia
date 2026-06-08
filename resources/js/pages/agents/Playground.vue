<script setup lang="ts">
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Check, Copy, ExternalLink, Globe, MessageSquare, Monitor, Search, Smartphone, Tablet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    authToken?: string | null;
}

const props = defineProps<Props>();

type Surface = 'hosted_chat' | 'widget' | 'bar';
type Device = 'desktop' | 'tablet' | 'mobile';

const surface = ref<Surface>('hosted_chat');
const device = ref<Device>('desktop');

const surfaceTabs: { value: Surface; label: string; icon: typeof MessageSquare }[] = [
    { value: 'hosted_chat', label: 'Chat', icon: MessageSquare },
    { value: 'widget', label: 'Widget', icon: Globe },
    { value: 'bar', label: 'Bar', icon: Search },
];

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

// Copy the agent's external id (the public identifier used in embed snippets,
// routes, and the API) straight from the preview toolbar.
const isExternalIdCopied = ref(false);
const copyExternalId = async () => {
    try {
        await navigator.clipboard.writeText(props.agent.external_id);
        isExternalIdCopied.value = true;
        toast.success('Agent ID copied to clipboard');
        setTimeout(() => (isExternalIdCopied.value = false), 2000);
    } catch {
        toast.error('Could not copy to clipboard');
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Playground" hide-header :no-padding="true" :full-height="true">
        <div class="bg-background flex h-full flex-col">
            <!-- Surface tabs (Setup-style underline nav) + device toggle + open-in-new-tab -->
            <div class="bg-background border-border flex flex-shrink-0 flex-wrap items-center justify-between gap-3 border-b pr-4 lg:pr-6">
                <nav class="flex items-center gap-1 px-4">
                    <button
                        v-for="tab in surfaceTabs"
                        :key="tab.value"
                        type="button"
                        @click="surface = tab.value"
                        class="relative flex items-center gap-2 px-4 py-3 text-sm transition-colors"
                        :class="[surface === tab.value ? 'text-foreground font-medium' : 'text-muted-foreground hover:text-foreground']"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        <span>{{ tab.label }}</span>
                        <span v-if="surface === tab.value" class="bg-foreground absolute right-0 -bottom-px left-0 h-0.5"></span>
                    </button>
                </nav>

                <TooltipProvider :delay-duration="0">
                    <div class="flex items-center gap-1 py-2">
                        <Tooltip v-for="d in ['desktop', 'tablet', 'mobile'] as const" :key="d">
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    @click="device = d"
                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-md p-1.5 transition-colors"
                                    :class="{ 'text-foreground bg-background shadow-sm': device === d }"
                                >
                                    <component :is="d === 'desktop' ? Monitor : d === 'tablet' ? Tablet : Smartphone" class="h-4 w-4" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p class="capitalize">{{ d }}</p>
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-md p-1.5 transition-colors"
                                    :class="{ 'text-foreground': isExternalIdCopied }"
                                    @click="copyExternalId"
                                >
                                    <component :is="isExternalIdCopied ? Check : Copy" class="h-4 w-4" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ isExternalIdCopied ? 'Copied!' : 'Copy agent ID' }}</p>
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-md p-1.5 transition-colors"
                                    @click="openFullPreview"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Open in new tab</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </div>

            <!-- Full-page preview frame. Desktop fills the area; tablet/mobile
                 use a fixed-width device frame so breakpoints are visible. -->
            <div class="bg-muted flex flex-1 items-center justify-center overflow-hidden">
                <div :class="frameClass" class="transition-all duration-300">
                    <iframe :key="`${surface}-${device}`" :src="currentUrl" class="h-full w-full border-0" title="Surface preview" loading="lazy" />
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
