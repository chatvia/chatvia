<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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
    <Head :title="t('consent.head_title')" />

    <div class="bg-background min-h-screen">
        <!-- Header -->
        <header class="bg-background/95 border-b backdrop-blur">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold">ChatVia</h1>
                    </div>
                    <div class="text-muted-foreground text-sm">{{ t('consent.header_subtitle') }}</div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="space-y-8">
                <!-- Page Title -->
                <div class="text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ t('consent.page_title') }}</h2>
                    <p class="text-muted-foreground mt-4 text-lg">{{ t('consent.intro') }}</p>
                    <p class="text-muted-foreground mt-2 text-sm">{{ t('consent.intro_note') }}</p>
                </div>

                <div class="bg-muted/50 rounded-lg border p-6">
                    <h3 class="mb-3 flex items-center text-lg font-semibold">
                        <svg class="me-2 h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        {{ t('consent.how_it_works_title') }}
                    </h3>
                    <div class="text-muted-foreground space-y-3 text-sm">
                        <p>{{ t('consent.how_it_works_intro') }}</p>
                        <ul class="ms-4 list-inside list-disc space-y-2">
                            <li>
                                <strong>{{ t('consent.infra_label') }}</strong> {{ t('consent.infra_text_before') }}<a
                                    href="https://cohere.com/privacy"
                                    class="text-primary hover:underline"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >{{ t('consent.privacy_policy_link') }}</a
                                >{{ t('consent.infra_text_after') }}
                            </li>
                            <li><strong>{{ t('consent.storage_label') }}</strong> {{ t('consent.storage_text') }}</li>
                            <li><strong>{{ t('consent.no_training_label') }}</strong> {{ t('consent.no_training_text') }}</li>
                            <li><strong>{{ t('consent.control_label') }}</strong> {{ t('consent.control_text') }}</li>
                        </ul>
                    </div>
                </div>

                <!-- Data Storage Region Selection -->
                <div class="bg-card rounded-lg border p-6 shadow-sm">
                    <h3 class="mb-4 text-xl font-semibold">{{ t('consent.region_title') }}</h3>
                    <p class="text-muted-foreground mb-6">{{ t('consent.region_description') }}</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <!-- EU Option -->
                        <div
                            class="cursor-pointer rounded-lg border-2 p-6 transition-all hover:shadow-md"
                            :class="selectedRegion === 'eu' ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'"
                            @click="selectRegion('eu')"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0">
                                    <div
                                        class="flex h-6 w-6 items-center justify-center rounded-full border-2"
                                        :class="selectedRegion === 'eu' ? 'border-primary bg-primary' : 'border-border'"
                                    >
                                        <div v-if="selectedRegion === 'eu'" class="h-2 w-2 rounded-full bg-white"></div>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="mb-2 flex items-center gap-3">
                                        <!-- EU Flag -->
                                        <span class="text-2xl">🇪🇺</span>
                                        <h4 class="text-lg font-semibold">{{ t('consent.eu_title') }}</h4>
                                    </div>
                                    <p class="text-muted-foreground mt-2 text-sm">{{ t('consent.eu_description') }}</p>
                                    <div class="mt-3 space-y-1">
                                        <div class="flex items-center text-sm text-green-600">
                                            <svg class="me-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ t('consent.eu_feature_1') }}
                                        </div>
                                        <div class="flex items-center text-sm text-green-600">
                                            <svg class="me-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ t('consent.eu_feature_2') }}
                                        </div>
                                        <div class="flex items-center text-sm text-green-600">
                                            <svg class="me-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ t('consent.eu_feature_3') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- US Option -->
                        <div
                            class="cursor-pointer rounded-lg border-2 p-6 transition-all hover:shadow-md"
                            :class="selectedRegion === 'us' ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'"
                            @click="selectRegion('us')"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0">
                                    <div
                                        class="flex h-6 w-6 items-center justify-center rounded-full border-2"
                                        :class="selectedRegion === 'us' ? 'border-primary bg-primary' : 'border-border'"
                                    >
                                        <div v-if="selectedRegion === 'us'" class="h-2 w-2 rounded-full bg-white"></div>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="mb-2 flex items-center gap-3">
                                        <!-- US Flag -->
                                        <span class="text-2xl">🇺🇸</span>
                                        <h4 class="text-lg font-semibold">{{ t('consent.us_title') }}</h4>
                                    </div>
                                    <p class="text-muted-foreground mt-2 text-sm">{{ t('consent.us_description') }}</p>
                                    <div class="mt-3 space-y-1">
                                        <div class="flex items-center text-sm text-blue-600">
                                            <svg class="me-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ t('consent.us_feature_1') }}
                                        </div>
                                        <div class="flex items-center text-sm text-blue-600">
                                            <svg class="me-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ t('consent.us_feature_2') }}
                                        </div>
                                        <div class="flex items-center text-sm text-blue-600">
                                            <svg class="me-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            {{ t('consent.us_feature_3') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Consent Options -->
                <div class="bg-card rounded-lg border p-6 shadow-sm">
                    <h3 class="mb-4 text-xl font-semibold">{{ t('consent.preferences_title') }}</h3>
                    <div class="space-y-4">
                        <!-- Marketing Consent -->
                        <div class="flex items-start gap-3">
                            <div class="flex h-5 items-center">
                                <input
                                    id="marketing"
                                    v-model="form.marketing_consent"
                                    type="checkbox"
                                    class="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                                />
                            </div>
                            <div class="ms-3 text-sm">
                                <label for="marketing" class="text-foreground cursor-pointer font-medium">{{ t('consent.marketing_label') }}</label>
                                <p class="text-muted-foreground">{{ t('consent.marketing_description') }}</p>
                            </div>
                        </div>

                        <!-- Analytics Consent -->
                        <div class="flex items-start gap-3">
                            <div class="flex h-5 items-center">
                                <input
                                    id="analytics"
                                    v-model="form.analytics_consent"
                                    type="checkbox"
                                    class="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                                />
                            </div>
                            <div class="ms-3 text-sm">
                                <label for="analytics" class="text-foreground cursor-pointer font-medium">{{ t('consent.analytics_label') }}</label>
                                <p class="text-muted-foreground">{{ t('consent.analytics_description') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GDPR Information -->
                <div class="bg-muted/50 rounded-lg border p-6">
                    <h3 class="mb-3 flex items-center text-lg font-semibold">
                        <svg class="me-2 h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        {{ t('consent.gdpr_title') }}
                    </h3>
                    <div class="text-muted-foreground space-y-2 text-sm">
                        <p>{{ t('consent.gdpr_intro') }}</p>
                        <ul class="ms-4 list-inside list-disc space-y-1">
                            <li><strong>{{ t('consent.right_access_label') }}</strong> {{ t('consent.right_access_text') }}</li>
                            <li><strong>{{ t('consent.right_rectification_label') }}</strong> {{ t('consent.right_rectification_text') }}</li>
                            <li><strong>{{ t('consent.right_erasure_label') }}</strong> {{ t('consent.right_erasure_text') }}</li>
                            <li><strong>{{ t('consent.right_portability_label') }}</strong> {{ t('consent.right_portability_text') }}</li>
                            <li><strong>{{ t('consent.right_object_label') }}</strong> {{ t('consent.right_object_text') }}</li>
                        </ul>
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <p class="text-amber-800">
                                <strong>{{ t('consent.important_label') }}</strong> {{ t('consent.important_text') }}
                            </p>
                        </div>
                        <p class="mt-3">
                            {{ t('consent.contact_text') }}
                            <a href="mailto:privacy@chatvia.com" class="text-primary hover:underline">privacy@chatvia.com</a>
                        </p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-center">
                    <button
                        @click="submitConsent"
                        :disabled="!selectedRegion || form.processing"
                        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center justify-center rounded-md px-8 py-3 text-base font-medium disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <svg v-if="form.processing" class="me-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="m4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        {{ form.processing ? t('consent.saving') : t('consent.save_preferences') }}
                    </button>
                </div>

                <!-- Footer Note -->
                <div class="text-muted-foreground text-center text-sm">
                    <p>
                        {{ t('consent.footer_before') }}
                        <a href="/privacy-policy" class="text-primary hover:underline">{{ t('consent.footer_privacy') }}</a>
                        {{ t('consent.footer_and') }}
                        <a href="/terms-of-service" class="text-primary hover:underline">{{ t('consent.footer_terms') }}</a>.
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>
