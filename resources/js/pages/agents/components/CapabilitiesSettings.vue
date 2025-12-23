<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { BarChart3, BookOpen, Brain, Code2, FileText, Globe, Languages, Search, Settings2, Shield, Sparkles, Zap } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    form: {
        capabilities: string[];
        features: string[];
        responseGuidelines: string;
        behaviorRules: string;
        prohibitedTopics: string;
        customInstructions: string;
    };
}>();

const capabilities = [
    {
        id: 'code',
        label: 'Code Generation',
        icon: Code2,
        description: 'Generate and explain code in various programming languages',
        category: 'technical',
    },
    {
        id: 'analysis',
        label: 'Data Analysis',
        icon: BarChart3,
        description: 'Analyze and interpret data, create visualizations',
        category: 'technical',
    },
    {
        id: 'writing',
        label: 'Content Writing',
        icon: FileText,
        description: 'Create and edit various types of content',
        category: 'creative',
    },
    {
        id: 'research',
        label: 'Research',
        icon: Search,
        description: 'Conduct thorough research and provide citations',
        category: 'academic',
    },
    {
        id: 'translation',
        label: 'Translation',
        icon: Languages,
        description: 'Translate between multiple languages',
        category: 'language',
    },
    {
        id: 'summarization',
        label: 'Summarization',
        icon: BookOpen,
        description: 'Summarize long texts and documents',
        category: 'content',
    },
    {
        id: 'creative',
        label: 'Creative Writing',
        icon: Sparkles,
        description: 'Generate creative content and ideas',
        category: 'creative',
    },
    {
        id: 'technical',
        label: 'Technical Support',
        icon: Settings2,
        description: 'Provide technical assistance and troubleshooting',
        category: 'technical',
    },
];

const features = [
    {
        id: 'multilingual',
        label: 'Multilingual Support',
        icon: Globe,
        description: 'Support for multiple languages',
    },
    {
        id: 'context_aware',
        label: 'Context Awareness',
        icon: Brain,
        description: 'Maintains conversation context',
    },
    {
        id: 'real_time',
        label: 'Real-time Processing',
        icon: Zap,
        description: 'Fast response times',
    },
    {
        id: 'secure',
        label: 'Enhanced Security',
        icon: Shield,
        description: 'Advanced security features',
    },
];

const groupedCapabilities = computed(() => {
    const groups = {} as Record<string, typeof capabilities>;
    capabilities.forEach((cap) => {
        if (!groups[cap.category]) {
            groups[cap.category] = [];
        }
        groups[cap.category].push(cap);
    });
    return groups;
});

const getCategoryLabel = (category: string) => {
    const labels: Record<string, string> = {
        technical: 'Technical',
        creative: 'Creative',
        academic: 'Academic',
        language: 'Language',
        content: 'Content',
    };
    return labels[category] || category;
};
</script>

<template>
    <div class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Capabilities & Features</CardTitle>
                <CardDescription> Select the capabilities and features your agent should have </CardDescription>
            </CardHeader>
            <CardContent class="space-y-8">
                <!-- Capabilities -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium">Core Capabilities</h3>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div v-for="(group, category) in groupedCapabilities" :key="category" class="space-y-4">
                            <h4 class="text-muted-foreground text-sm font-medium">
                                {{ getCategoryLabel(category) }}
                            </h4>
                            <div class="space-y-3">
                                <div v-for="capability in group" :key="capability.id" class="flex items-start space-x-3">
                                    <div class="flex h-5 items-center">
                                        <input
                                            type="checkbox"
                                            :id="capability.id"
                                            :value="capability.id"
                                            v-model="form.capabilities"
                                            class="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <Label :for="capability.id" class="flex items-center gap-2">
                                            <component :is="capability.icon" class="h-4 w-4" />
                                            {{ capability.label }}
                                        </Label>
                                        <p class="text-muted-foreground text-sm">
                                            {{ capability.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Features -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium">Additional Features</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="feature in features" :key="feature.id" class="flex items-start space-x-3">
                            <div class="flex h-5 items-center">
                                <input
                                    type="checkbox"
                                    :id="feature.id"
                                    :value="feature.id"
                                    v-model="form.features"
                                    class="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label :for="feature.id" class="flex items-center gap-2">
                                    <component :is="feature.icon" class="h-4 w-4" />
                                    {{ feature.label }}
                                </Label>
                                <p class="text-muted-foreground text-sm">
                                    {{ feature.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Response Guidelines -->
        <Card>
            <CardHeader>
                <CardTitle>Response Guidelines</CardTitle>
                <CardDescription> Define how your agent should respond to different situations </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-2">
                    <Label for="responseGuidelines">Response Guidelines</Label>
                    <Textarea
                        id="responseGuidelines"
                        v-model="form.responseGuidelines"
                        placeholder="Specify how your agent should respond to different types of queries"
                        rows="4"
                        required
                    />
                </div>
                <div class="space-y-2">
                    <Label for="behaviorRules">Behavior Rules</Label>
                    <Textarea
                        id="behaviorRules"
                        v-model="form.behaviorRules"
                        placeholder="Define specific rules and limitations for your agent's behavior"
                        rows="4"
                        required
                    />
                </div>
                <div class="space-y-2">
                    <Label for="prohibitedTopics">Prohibited Topics</Label>
                    <Textarea
                        id="prohibitedTopics"
                        v-model="form.prohibitedTopics"
                        placeholder="List topics or subjects that the agent should avoid"
                        rows="3"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="customInstructions">Custom Instructions</Label>
                    <Textarea
                        id="customInstructions"
                        v-model="form.customInstructions"
                        placeholder="Add any additional custom instructions for your agent"
                        rows="3"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
