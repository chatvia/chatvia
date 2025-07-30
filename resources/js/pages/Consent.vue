<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    data_region: '',
    marketing_consent: false,
    analytics_consent: false,
});

const selectedRegion = ref('');

const selectRegion = (region: string) => {
    selectedRegion.value = region;
    form.data_region = region;
};

const submitConsent = () => {
    form.post('/consent', {
        onSuccess: () => {
            // Handle success - could redirect to dashboard or show confirmation
        },
    });
};
</script>

<template>
    <Head title="Data Processing Consent - ChatVia" />

    <div class="min-h-screen bg-background">
        <!-- Header -->
        <header class="border-b bg-background/95 backdrop-blur">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold">ChatVia</h1>
                    </div>
                    <div class="text-sm text-muted-foreground">
                        Data Processing Consent
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="space-y-8">
                <!-- Page Title -->
                <div class="text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Data Processing Consent</h2>
                    <p class="mt-4 text-lg text-muted-foreground">
                        Before you start using ChatVia, we need to know how you want your data to be processed. This page will help you understand your rights and choices.
                    </p>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Please note that this page describes the data processing, which is a preliminary stage to make your data ready for your agent.
                    </p>
                </div>

                <div class="rounded-lg border bg-muted/50 p-6">
                    <h3 class="text-lg font-semibold mb-3 flex items-center">
                        <svg class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        How ChatVia Works
                    </h3>
                    <div class="text-sm text-muted-foreground space-y-3">
                        <p>
                            When you create a training-based agent, ChatVia will send your data to different providers for processing. Here are the key points about our data processing:
                        </p>
                        <ul class="list-disc list-inside space-y-2 ml-4">
                            <li><strong>Data Processing Providers:</strong> We use Cohere (<a href="https://cohere.com/privacy" class="text-primary hover:underline" target="_blank" rel="noopener noreferrer">privacy policy</a>), Mistral for EU users, and Google/OpenAI for US users.</li>
                            <li><strong>Data Storage:</strong> Your data will be stored and encrypted locally in the EU (Germany/Finland).</li>
                            <li><strong>No Training:</strong> We don't train models on your data. Instead, we use RAG (Retrieval-Augmented Generation) and GraphRAG to find relevant information and send it to your chosen model for processing.</li>
                            <li><strong>Data Control:</strong> You can easily delete your data at any time through your account settings.</li>
                        </ul>
                    </div>
                </div>

                <!-- Data Storage Region Selection -->
                <div class="rounded-lg border bg-card p-6 shadow-sm">
                    <h3 class="text-xl font-semibold mb-4">Choose Your Data Storage Region</h3>
                    <p class="text-muted-foreground mb-6">
                        Select where you would like your data to be stored and processed. This choice affects which data protection laws apply to your information.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <!-- EU Option -->
                        <div
                            class="cursor-pointer rounded-lg border-2 p-6 transition-all hover:shadow-md"
                            :class="selectedRegion === 'eu'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50'"
                            @click="selectRegion('eu')"
                        >
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="h-6 w-6 rounded-full border-2 flex items-center justify-center"
                                         :class="selectedRegion === 'eu'
                                             ? 'border-primary bg-primary'
                                             : 'border-border'">
                                        <div v-if="selectedRegion === 'eu'" class="h-2 w-2 rounded-full bg-white"></div>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <!-- EU Flag -->
                                        <span class="text-2xl">🇪🇺</span>
                                        <h4 class="text-lg font-semibold">European Union (EU)</h4>
                                    </div>
                                    <p class="text-sm text-muted-foreground mt-2">
                                        Your data will be stored and processed within the European Union, ensuring full GDPR compliance and maximum privacy protection.
                                    </p>
                                    <div class="mt-3 space-y-1">
                                        <div class="flex items-center text-sm text-green-600">
                                            <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Full GDPR protection
                                        </div>
                                        <div class="flex items-center text-sm text-green-600">
                                            <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Data remains within EU borders
                                        </div>
                                        <div class="flex items-center text-sm text-green-600">
                                            <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Processed using Mistral AI
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- US Option -->
                        <div
                            class="cursor-pointer rounded-lg border-2 p-6 transition-all hover:shadow-md"
                            :class="selectedRegion === 'us'
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary/50'"
                            @click="selectRegion('us')"
                        >
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="h-6 w-6 rounded-full border-2 flex items-center justify-center"
                                         :class="selectedRegion === 'us'
                                             ? 'border-primary bg-primary'
                                             : 'border-border'">
                                        <div v-if="selectedRegion === 'us'" class="h-2 w-2 rounded-full bg-white"></div>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <!-- US Flag -->
                                        <span class="text-2xl">🇺🇸</span>
                                        <h4 class="text-lg font-semibold">United States (US)</h4>
                                    </div>
                                    <p class="text-sm text-muted-foreground mt-2">
                                        Your data will be stored and processed in the United States, with strong privacy protections and access to the latest AI infrastructure. This region provides direct access to leading AI providers.
                                    </p>
                                    <div class="mt-3 space-y-1">
                                        <div class="flex items-center text-sm text-blue-600">
                                            <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Access to OpenAI and Google AI
                                        </div>
                                        <div class="flex items-center text-sm text-blue-600">
                                            <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Optimized performance and speed
                                        </div>
                                        <div class="flex items-center text-sm text-blue-600">
                                            <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Strong data protection standards
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Consent Options -->
                <div class="rounded-lg border bg-card p-6 shadow-sm">
                    <h3 class="text-xl font-semibold mb-4">Additional Preferences</h3>
                    <div class="space-y-4">
                        <!-- Marketing Consent -->
                        <div class="flex items-start space-x-3">
                            <div class="flex items-center h-5">
                                <input
                                    id="marketing"
                                    v-model="form.marketing_consent"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                >
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="marketing" class="font-medium text-foreground cursor-pointer">
                                    Marketing Communications
                                </label>
                                <p class="text-muted-foreground">
                                    Receive updates about new features, tips, and special offers. You can unsubscribe at any time.
                                </p>
                            </div>
                        </div>

                        <!-- Analytics Consent -->
                        <div class="flex items-start space-x-3">
                            <div class="flex items-center h-5">
                                <input
                                    id="analytics"
                                    v-model="form.analytics_consent"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                >
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="analytics" class="font-medium text-foreground cursor-pointer">
                                    Analytics & Performance
                                </label>
                                <p class="text-muted-foreground">
                                    Help us improve ChatVia by allowing anonymous usage analytics and performance monitoring.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GDPR Information -->
                <div class="rounded-lg border bg-muted/50 p-6">
                    <h3 class="text-lg font-semibold mb-3 flex items-center">
                        <svg class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Your Rights Under GDPR
                    </h3>
                    <div class="text-sm text-muted-foreground space-y-2">
                        <p>Regardless of your chosen region, if you are an EU resident, you have the following rights under GDPR:</p>
                        <ul class="list-disc list-inside space-y-1 ml-4">
                            <li><strong>Right to Access:</strong> Request a copy of your personal data</li>
                            <li><strong>Right to Rectification:</strong> Correct inaccurate or incomplete data</li>
                            <li><strong>Right to Erasure:</strong> Request deletion of your personal data</li>
                            <li><strong>Right to Portability:</strong> Transfer your data to another service</li>
                            <li><strong>Right to Object:</strong> Object to processing of your personal data</li>
                        </ul>
                        <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <p class="text-amber-800">
                                <strong>Important:</strong> When using AI models from third-party providers (such as OpenAI, Google, or Mistral), your chat interactions may be subject to their respective privacy policies and data processing practices. We recommend reviewing their privacy policies for complete transparency.
                            </p>
                        </div>
                        <p class="mt-3">
                            To exercise these rights or for any privacy-related questions, contact us at <a href="mailto:privacy@chatvia.com" class="text-primary hover:underline">privacy@chatvia.com</a>
                        </p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-center">
                    <button
                        @click="submitConsent"
                        :disabled="!selectedRegion || form.processing"
                        class="inline-flex items-center justify-center rounded-md bg-primary px-8 py-3 text-base font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg v-if="form.processing" class="h-4 w-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ form.processing ? 'Saving...' : 'Save Preferences' }}
                    </button>
                </div>

                <!-- Footer Note -->
                <div class="text-center text-sm text-muted-foreground">
                    <p>
                        By clicking "Save Preferences", you acknowledge that you have read and understood our
                        <a href="/privacy-policy" class="text-primary hover:underline">Privacy Policy</a> and
                        <a href="/terms-of-service" class="text-primary hover:underline">Terms of Service</a>.
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>
