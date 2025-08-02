<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';

import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Palette, Upload, Trash2, LoaderCircle, Save, User, RotateCcw, Image } from 'lucide-vue-next';
import FormSuccessMessage from '@/components/FormSuccessMessage.vue';

interface Props {
    agent: any;
    settings: any;
    default_settings: any;
}

const props = defineProps<Props>();

// Form for appearance settings
const appearanceForm = useForm({
    color_theme: props.settings.color_theme || 'black',
    color_shade: props.settings.color_shade || 500,
    welcome_message: props.settings.welcome_message,
    chat_window_title: props.settings.chat_window_title,
    chat_footer_text: props.settings.chat_footer_text,
    composer_placeholder: props.settings.composer_placeholder,
    avatar: null as File | null,
    logo: null as File | null
});

// States
const isSubmitting = ref(false);
const showSuccessMessage = ref(false);
const showColorPicker = ref(false);
const avatarPreview = ref<string | null>(props.settings.avatar || null);
const logoPreview = ref<string | null>(props.settings.logo || null);
const avatarInputRef = ref<HTMLInputElement | null>(null);
const logoInputRef = ref<HTMLInputElement | null>(null);

const updateAppearance = () => {
    isSubmitting.value = true;
    showSuccessMessage.value = false;
    
    appearanceForm.post(route('agents.settings.update', { agent: props.agent.id, group: 'appearance' }), {
        preserveScroll: true,
        forceFormData: true,

        onSuccess: () => {
            isSubmitting.value = false;
            showSuccessMessage.value = true;
            setTimeout(() => {
                showSuccessMessage.value = false;
            }, 3000);
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const triggerAvatarInput = () => {
    if (avatarInputRef.value) {
        avatarInputRef.value.click();
    }
};

const handleAvatarUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    // Set the file in the form
    appearanceForm.avatar = file;

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
        if (e.target && typeof e.target.result === 'string') {
            avatarPreview.value = e.target.result;
        }
    };
    reader.readAsDataURL(file);
};

const removeAvatar = () => {
    router.delete(route('agents.settings.delete', { 
        agent: props.agent.id, 
        group: 'appearance', 
        key: 'avatar' 
    }), {
        preserveScroll: true,
        onSuccess: () => {
            avatarPreview.value = null;
            if (avatarInputRef.value) {
                avatarInputRef.value.value = '';
            }
            showSuccessMessage.value = true;
            setTimeout(() => {
                showSuccessMessage.value = false;
            }, 3000);
        },
        onError: () => {
            console.error('Failed to delete avatar');
        }
    });
};

const triggerLogoInput = () => {
    if (logoInputRef.value) {
        logoInputRef.value.click();
    }
};

const handleLogoUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    // Set the file in the form
    appearanceForm.logo = file;

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
        if (e.target && typeof e.target.result === 'string') {
            logoPreview.value = e.target.result;
        }
    };
    reader.readAsDataURL(file);
};

const removeLogo = () => {
    router.delete(route('agents.settings.delete', { 
        agent: props.agent.id, 
        group: 'appearance', 
        key: 'logo' 
    }), {
        preserveScroll: true,
        onSuccess: () => {
            logoPreview.value = null;
            if (logoInputRef.value) {
                logoInputRef.value.value = '';
            }
            showSuccessMessage.value = true;
            setTimeout(() => {
                showSuccessMessage.value = false;
            }, 3000);
        },
        onError: () => {
            console.error('Failed to delete logo');
        }
    });
};

const restoreDefaults = () => {
    // Reset all form fields to default values
    appearanceForm.color_theme = props.default_settings.color_theme || 'blue';
    appearanceForm.color_shade = props.default_settings.color_shade || 500;
    appearanceForm.welcome_message = props.default_settings.welcome_message;
    appearanceForm.chat_window_title = props.default_settings.chat_window_title;
    appearanceForm.chat_footer_text = props.default_settings.chat_footer_text;
    appearanceForm.composer_placeholder = props.default_settings.composer_placeholder;
    appearanceForm.avatar = null;
    appearanceForm.logo = null;
    
    // Reset previews
    avatarPreview.value = null;
    logoPreview.value = null;
    if (avatarInputRef.value) {
        avatarInputRef.value.value = '';
    }
    if (logoInputRef.value) {
        logoInputRef.value.value = '';
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Appearance Settings">
        <div class="space-y-6">
            <form @submit.prevent="updateAppearance">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Palette class="h-5 w-5" />
                        Appearance
                    </CardTitle>
                    <CardDescription>
                        Customize how your agent looks
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6">
                        <div class="grid gap-2">
                            <Label for="color_theme">Color Theme</Label>
                            <div class="flex items-center gap-3">
                                <!-- Selected Color Preview -->
                                <div class="relative cursor-pointer" @click="showColorPicker = true">
                                    <div
                                        class="h-12 w-12 rounded-lg border-2 border-border shadow-sm transition-all duration-200 hover:shadow-md hover:scale-105"
                                        :style="{
                                            backgroundColor: `var(--${appearanceForm.color_theme}-${appearanceForm.color_shade})`
                                        }"
                                    >
                                        <div class="flex h-full items-center justify-center">
                                            <div class="h-6 w-6 rounded-full bg-white/90 shadow-sm flex items-center justify-center">
                                                <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: `var(--${appearanceForm.color_theme}-${appearanceForm.color_shade})` }"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Color Info and Button -->
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <p class="text-sm font-medium capitalize">{{ appearanceForm.color_theme }}</p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="showColorPicker = true"
                                            class="h-7 px-3 text-xs"
                                        >
                                            Choose Color Theme
                                        </Button>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        Current theme: {{ appearanceForm.color_theme.charAt(0).toUpperCase() + appearanceForm.color_theme.slice(1) }}
                                    </p>
                                </div>
                            </div>
                            
                            <p v-if="appearanceForm.errors.color_theme" class="text-sm text-destructive">
                                {{ appearanceForm.errors.color_theme }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Choose a color theme for your agent.
                            </p>
                        </div>



                        <div class="grid gap-2">
                            <Label for="avatar">Agent Avatar</Label>
                            <div class="flex items-center gap-4">
                                <div class="h-16 w-16 overflow-hidden rounded-full border bg-muted">
                                    <img
                                        v-if="avatarPreview"
                                        :src="avatarPreview"
                                        alt="Agent avatar"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full w-full items-center justify-center text-muted-foreground">
                                        <User class="h-8 w-8" />
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <input
                                        ref="avatarInputRef"
                                        accept="image/*"
                                        class="hidden"
                                        type="file"
                                        @change="handleAvatarUpload"
                                    />
                                    <Button type="button" variant="outline" @click="triggerAvatarInput">
                                        <Upload class="mr-2 h-4 w-4" />
                                        Upload Avatar
                                    </Button>
                                    <Button
                                        v-if="avatarPreview"
                                        class="text-destructive hover:text-destructive/80"
                                        size="sm"
                                        type="button"
                                        variant="ghost"
                                        @click="removeAvatar"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        Remove
                                    </Button>
                                </div>
                            </div>
                            <p v-if="appearanceForm.errors.avatar" class="text-sm text-destructive">
                                {{ appearanceForm.errors.avatar }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Recommended size: 256x256 pixels
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="logo">Logo Image</Label>
                            <div class="flex items-center gap-4">
                                <div class="h-16 w-16 overflow-hidden rounded-full border bg-muted">
                                    <img
                                        v-if="logoPreview"
                                        :src="logoPreview"
                                        alt="Logo"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full w-full items-center justify-center text-muted-foreground">
                                        <Image class="h-8 w-8" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>

                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <input
                                        ref="logoInputRef"
                                        accept="image/*"
                                        class="hidden"
                                        type="file"
                                        @change="handleLogoUpload"
                                    />
                                    <Button type="button" variant="outline" @click="triggerLogoInput">
                                        <Upload class="mr-2 h-4 w-4" />
                                        Upload Logo
                                    </Button>
                                    <Button
                                        v-if="logoPreview"
                                        class="text-destructive hover:text-destructive/80"
                                        size="sm"
                                        type="button"
                                        variant="ghost"
                                        @click="removeLogo"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        Remove
                                    </Button>
                                </div>
                            </div>
                            <p v-if="appearanceForm.errors.logo" class="text-sm text-destructive">
                                {{ appearanceForm.errors.logo }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Recommended size: 256x256 pixels
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="welcome_message">Welcome Message</Label>
                            <Textarea
                                id="welcome_message"
                                v-model="appearanceForm.welcome_message"
                                :error="appearanceForm.errors.welcome_message"
                                placeholder="Hi there! How can I help you today?"
                                rows="3"
                            />
                            <p v-if="appearanceForm.errors.welcome_message" class="text-sm text-destructive">
                                {{ appearanceForm.errors.welcome_message }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                This message will be shown when users first visit your agent
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="chat_window_title">Chat Window Title</Label>
                            <Input
                                id="chat_window_title"
                                v-model="appearanceForm.chat_window_title"
                                :error="appearanceForm.errors.chat_window_title"
                                placeholder="Chat with our AI Assistant"
                            />
                            <p v-if="appearanceForm.errors.chat_window_title" class="text-sm text-destructive">
                                {{ appearanceForm.errors.chat_window_title }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="chat_footer_text">Chat Footer Text</Label>
                            <Input
                                id="chat_footer_text"
                                v-model="appearanceForm.chat_footer_text"
                                :error="appearanceForm.errors.chat_footer_text"
                                placeholder="Consider checking important information"
                            />
                            <p v-if="appearanceForm.errors.chat_footer_text" class="text-sm text-destructive">
                                {{ appearanceForm.errors.chat_footer_text }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="composer_placeholder">Composer Placeholder</Label>
                            <Input
                                id="composer_placeholder"
                                v-model="appearanceForm.composer_placeholder"
                                :error="appearanceForm.errors.composer_placeholder"
                                placeholder="Ask anything..."
                            />
                            <p v-if="appearanceForm.errors.composer_placeholder" class="text-sm text-destructive">
                                {{ appearanceForm.errors.composer_placeholder }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Placeholder text shown in the chat input field
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Actions -->
            <div class="flex items-center justify-between pt-6">
                <Button
                    type="button"
                    variant="outline"
                    @click="restoreDefaults"
                    class="gap-2"
                >
                    <RotateCcw class="h-4 w-4" />
                    Restore Default Settings
                </Button>
                
                <div class="flex items-center gap-3">
                    <FormSuccessMessage 
                        :show="showSuccessMessage" 
                        message="Appearance settings saved successfully" 
                    />
                    
                    <!-- General form errors -->
                    <div v-if="Object.keys(appearanceForm.errors).length > 0 && !showSuccessMessage" class="text-sm text-destructive">
                        Please fix the errors above and try again.
                    </div>
                    
                    <Button 
                        :disabled="isSubmitting" 
                        size="lg" 
                        type="submit"
                        class="gap-2 min-w-[160px]"
                    >
                        <Save v-if="!isSubmitting" class="h-4 w-4" />
                        <div v-else class="h-4 w-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                        {{ isSubmitting ? 'Saving...' : 'Save Appearance' }}
                    </Button>
                </div>
            </div>
        </form>
        </div>

        <!-- Color Picker Dialog -->
        <Dialog :open="showColorPicker" @update:open="showColorPicker = false">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Select Color Theme</DialogTitle>
                    <DialogDescription>
                        Choose from 23 carefully crafted color themes. Each theme is optimized for both light and dark modes.
                    </DialogDescription>
                </DialogHeader>
                
                <div class="grid grid-cols-8 gap-2">
                    <div
                        v-for="theme in ['black', 'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose', 'slate', 'gray', 'zinc', 'neutral', 'stone']"
                        :key="theme"
                        class="relative cursor-pointer group"
                        @click="appearanceForm.color_theme = theme"
                    >
                        <div
                            class="h-12 w-full rounded-lg border-2 transition-all duration-200 shadow-sm"
                            :class="{
                                'border-primary ring-2 ring-primary/20': appearanceForm.color_theme === theme,
                                'border-border hover:border-primary/50 hover:shadow-md': appearanceForm.color_theme !== theme
                            }"
                            :style="{
                                backgroundColor: `var(--${theme}-${appearanceForm.color_shade})`
                            }"
                        >
                            <div class="flex h-full items-center justify-center">
                                <div
                                    v-if="appearanceForm.color_theme === theme"
                                    class="h-6 w-6 rounded-full bg-white shadow-lg flex items-center justify-center transform scale-100 transition-transform duration-200"
                                >
                                    <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: `var(--${theme}-${appearanceForm.color_shade})` }"></div>
                                </div>
                                <div
                                    v-else
                                    class="h-6 w-6 rounded-full bg-white/80 shadow-sm flex items-center justify-center transform scale-75 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-200"
                                >
                                    <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: `var(--${theme}-${appearanceForm.color_shade})` }"></div>
                                </div>
                            </div>
                        </div>
                        <p class="mt-1 text-center text-xs font-medium capitalize transition-colors duration-200" :class="{
                            'text-primary': appearanceForm.color_theme === theme,
                            'text-muted-foreground': appearanceForm.color_theme !== theme
                        }">{{ theme }}</p>
                    </div>
                </div>

                <!-- Color Shade Slider -->
                <div class="mt-6 pt-6 border-t">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <Label class="text-sm font-medium">Adjust Shade</Label>
                            <span class="text-xs text-muted-foreground">Light to Dark</span>
                        </div>
                        <div class="relative">
                            <input
                                type="range"
                                min="50"
                                max="950"
                                step="50"
                                v-model="appearanceForm.color_shade"
                                class="w-full h-3 bg-gray-200 rounded-lg appearance-none cursor-pointer slider"
                                :style="{
                                    background: `linear-gradient(to right, var(--${appearanceForm.color_theme}-50), var(--${appearanceForm.color_theme}-950))`
                                }"
                                @input="(e: Event) => {
                                    const target = e.target as HTMLInputElement;
                                    const value = parseInt(target.value);
                                    const validShades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
                                    const closest = validShades.reduce((prev, curr) => 
                                        Math.abs(curr - value) < Math.abs(prev - value) ? curr : prev
                                    );
                                    appearanceForm.color_shade = closest;
                                }"
                            />
                        </div>
                        <div class="flex justify-between text-xs text-muted-foreground">
                            <span>Light</span>
                            <span>Medium</span>
                            <span>Dark</span>
                        </div>
                    </div>
                </div>
                
                <DialogFooter>
                    <Button variant="outline" @click="showColorPicker = false">
                        Cancel
                    </Button>
                    <Button @click="showColorPicker = false">
                        Apply
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
