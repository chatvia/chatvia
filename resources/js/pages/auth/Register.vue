<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Info, ExternalLink, User, Mail, Lock, LockKeyhole } from 'lucide-vue-next';
import { ref } from 'vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    data_region: '',
});

const showConsentModal = ref(false);
const selectedRegion = ref('');

const selectRegion = (region: string) => {
    selectedRegion.value = region;
    form.data_region = region;
};

const closeModal = () => {
    showConsentModal.value = false;
};

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Create an account" description="Enter your details below to create your account">
        <Head title="Register" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="space-y-2">
                    <Label for="name" class="text-base font-medium">Name</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <User class="h-5 w-5" />
                        </div>
                        <Input 
                            id="name" 
                            type="text" 
                            required 
                            autofocus 
                            :tabindex="1" 
                            autocomplete="name" 
                            v-model="form.name" 
                            placeholder="Full name"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <Label for="email" class="text-base font-medium">Email address</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <Mail class="h-5 w-5" />
                        </div>
                        <Input 
                            id="email" 
                            type="email" 
                            required 
                            :tabindex="2" 
                            autocomplete="email" 
                            v-model="form.email" 
                            placeholder="email@example.com"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <div class="space-y-2">
                    <Label for="password" class="text-base font-medium">Password</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            v-model="form.password"
                            placeholder="Password"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="space-y-2">
                    <Label for="password_confirmation" class="text-base font-medium">Confirm password</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <LockKeyhole class="h-5 w-5" />
                        </div>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            placeholder="Confirm password"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Data Processing Consent Section -->
                <div class="border-t pt-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium">Data Processing Preferences</h3>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="showConsentModal = true"
                                class="text-xs text-muted-foreground hover:text-foreground"
                            >
                                <Info class="h-3 w-3 mr-1" />
                                Learn more
                            </Button>
                        </div>

                        <!-- Region Selection -->
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div
                                    class="cursor-pointer rounded-lg border-2 p-3 transition-all hover:shadow-sm"
                                    :class="form.data_region === 'eu'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50'"
                                    @click="selectRegion('eu')"
                                >
                                    <div class="flex items-center space-x-2">
                                        <div class="h-4 w-4 rounded-full border-2 flex items-center justify-center"
                                             :class="form.data_region === 'eu'
                                                 ? 'border-primary bg-primary'
                                                 : 'border-border'">
                                            <div v-if="form.data_region === 'eu'" class="h-1.5 w-1.5 rounded-full bg-white"></div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm">🇪🇺</span>
                                            <span class="text-sm font-medium">EU</span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="cursor-pointer rounded-lg border-2 p-3 transition-all hover:shadow-sm"
                                    :class="form.data_region === 'us'
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50'"
                                    @click="selectRegion('us')"
                                >
                                    <div class="flex items-center space-x-2">
                                        <div class="h-4 w-4 rounded-full border-2 flex items-center justify-center"
                                             :class="form.data_region === 'us'
                                                 ? 'border-primary bg-primary'
                                                 : 'border-border'">
                                            <div v-if="form.data_region === 'us'" class="h-1.5 w-1.5 rounded-full bg-white"></div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm">🇺🇸</span>
                                            <span class="text-sm font-medium">US</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors.data_region" />
                        </div>
                    </div>
                </div>

                <Button type="submit" class="mt-2 w-full h-12 text-base font-semibold" tabindex="5" :disabled="form.processing || !form.data_region">
                    <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin mr-2" />
                    <span v-if="!form.processing">Create account</span>
                    <span v-else>Creating account...</span>
                </Button>
            </div>

            <div class="text-center text-base text-muted-foreground">
                Already have an account?
                <TextLink :href="route('login')" class="font-semibold text-primary hover:text-primary/80 ml-1" :tabindex="6">Log in</TextLink>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                By creating an account, you agree to our
                <a href="/privacy-policy" class="text-primary hover:underline">Privacy Policy</a> and
                <a href="/terms-of-service" class="text-primary hover:underline">Terms of Service</a>.
            </div>
        </form>

        <!-- Consent Details Modal -->
        <div v-if="showConsentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="bg-background rounded-lg shadow-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-background border-b px-6 py-4 flex items-center justify-between">
                    <h2 class="text-xl font-semibold">Data Processing Information</h2>
                    <Button variant="ghost" size="sm" @click="closeModal">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </Button>
                </div>

                <div class="p-6 space-y-6">
                    <!-- How ChatVia Works -->
                    <div class="rounded-lg border bg-muted/50 p-4">
                        <h3 class="text-lg font-semibold mb-3 flex items-center">
                            <Info class="h-5 w-5 mr-2 text-blue-600" />
                            How ChatVia Works
                        </h3>
                        <div class="text-sm text-muted-foreground space-y-3">
                            <p>
                                When you create a training-based agent, ChatVia will send your data to different providers for processing. Here are the key points about our data processing:
                            </p>
                            <ul class="list-disc list-inside space-y-2 ml-4">
                                <li><strong>Data Processing Providers:</strong> We use Cohere (<a href="https://cohere.com/privacy" class="text-primary hover:underline" target="_blank" rel="noopener noreferrer">privacy policy <ExternalLink class="h-3 w-3 inline" /></a>), Mistral for EU users, and Google/OpenAI for US users.</li>
                                <li><strong>Data Storage:</strong> Your data will be stored and encrypted locally in the EU (Germany/Finland).</li>
                                <li><strong>No Training:</strong> We don't train models on your data. Instead, we use RAG (Retrieval-Augmented Generation) and GraphRAG to find relevant information and send it to your chosen model for processing.</li>
                                <li><strong>Data Control:</strong> You can easily delete your data at any time through your account settings.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Region Details -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <!-- EU Details -->
                        <div class="rounded-lg border p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="text-2xl">🇪🇺</span>
                                <h4 class="text-lg font-semibold">European Union (EU)</h4>
                            </div>
                            <p class="text-sm text-muted-foreground mb-3">
                                Your data will be stored and processed within the European Union, ensuring full GDPR compliance and maximum privacy protection.
                            </p>
                            <div class="space-y-2">
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

                        <!-- US Details -->
                        <div class="rounded-lg border p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="text-2xl">🇺🇸</span>
                                <h4 class="text-lg font-semibold">United States (US)</h4>
                            </div>
                            <p class="text-sm text-muted-foreground mb-3">
                                Your data will be stored and processed in the United States, with strong privacy protections and access to the latest AI infrastructure. This region provides direct access to leading AI providers.
                            </p>
                            <div class="space-y-2">
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

                    <!-- GDPR Information -->
                    <div class="rounded-lg border bg-muted/50 p-4">
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
                </div>

                <div class="sticky bottom-0 bg-background border-t px-6 py-4">
                    <Button @click="closeModal" class="w-full">
                        Close
                    </Button>
                </div>
            </div>
        </div>
    </AuthBase>
</template>
