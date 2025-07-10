<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Save, ArrowLeft } from 'lucide-vue-next';
import CreateAgentLayout from '@/layouts/AgentLayout.vue';
import BasicInformation from '../components/BasicInformation.vue';
import PersonalitySettings from '../components/PersonalitySettings.vue';
import CapabilitiesSettings from '../components/CapabilitiesSettings.vue';
import AdvancedSettings from '../components/AdvancedSettings.vue';

const activeTab = ref('basic');

const form = useForm({
    // Basic Information
    name: '',
    subdomain: '',
    description: '',
    category: '',
    language: 'en',
    timezone: 'UTC',

    // Personality & Communication
    personality: '',
    tone: 'professional',
    formality: 'balanced',
    responseStyle: 'concise',
    useEmojis: false,
    useMarkdown: true,

    // Advanced Settings
    maxResponseLength: 'medium',
    temperature: 0.7,
    contextWindow: 'large',
    fallbackBehavior: 'ask_user',
    errorHandling: 'graceful',

    // Capabilities & Features
    capabilities: [] as string[],
    features: [] as string[],

    // Response Guidelines
    responseGuidelines: '',
    behaviorRules: '',
    prohibitedTopics: '',
    customInstructions: '',

    // Security & Privacy
    dataRetention: '30days',
    privacyLevel: 'standard',
    contentFiltering: true,
    userDataHandling: 'minimal',
});

const submit = () => {
    form.post(route('agents.store'), {
        onSuccess: () => {
            router.visit(route('agents.index'));
        },
    });
};

const goBack = () => {
    router.visit(route('agents.select'));
};
</script>

<template>
    <CreateAgentLayout
        title="Create Agent with Instructions"
        v-model:activeTab="activeTab"
    >
        <form @submit.prevent="submit" class="space-y-8">
            <!-- Basic Information -->
            <BasicInformation
                v-if="activeTab === 'basic'"
                :form="form"
            />

            <!-- Personality Settings -->
            <PersonalitySettings
                v-if="activeTab === 'personality'"
                :form="form"
            />

            <!-- Capabilities Settings -->
            <CapabilitiesSettings
                v-if="activeTab === 'capabilities'"
                :form="form"
            />

            <!-- Advanced Settings -->
            <AdvancedSettings
                v-if="activeTab === 'advanced'"
                :form="form"
            />

            <!-- Submit Button -->
            <div class="flex items-center justify-between">
                <Button type="button" variant="outline" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back
                </Button>
                <div class="flex items-center gap-4">
                    <Button type="button" variant="outline">
                        Save as Draft
                    </Button>
                    <Button type="submit" size="lg" :disabled="form.processing">
                        <Save class="mr-2 h-4 w-4" />
                        Create Agent
                    </Button>
                </div>
            </div>
        </form>
    </CreateAgentLayout>
</template>
