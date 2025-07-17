<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';

import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Palette, Upload, Trash2, LoaderCircle, Save, User, RotateCcw } from 'lucide-vue-next';
import FormSuccessMessage from '@/components/FormSuccessMessage.vue';

interface Props {
    agent: any;
    settings: any;
    default_settings: any;
}

const props = defineProps<Props>();

// Form for appearance settings
const appearanceForm = useForm({
    primary_color: props.settings.primary_color,
    chat_bubble_color: props.settings.chat_bubble_color,
    chat_text_color: props.settings.chat_text_color,
    welcome_message: props.settings.welcome_message,
    chat_window_title: props.settings.chat_window_title,
    chat_footer_text: props.settings.chat_footer_text,
    avatar: null as File | null
});

// States
const isSubmitting = ref(false);
const showSuccessMessage = ref(false);
const avatarPreview = ref<string | null>(props.settings.avatar || null);
const avatarInputRef = ref<HTMLInputElement | null>(null);

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

const restoreDefaults = () => {
    // Reset all form fields to default values
    appearanceForm.primary_color = props.default_settings.primary_color;
    appearanceForm.chat_bubble_color = props.default_settings.chat_bubble_color;
    appearanceForm.chat_text_color = props.default_settings.chat_text_color;
    appearanceForm.welcome_message = props.default_settings.welcome_message;
    appearanceForm.chat_window_title = props.default_settings.chat_window_title;
    appearanceForm.chat_footer_text = props.default_settings.chat_footer_text;
    appearanceForm.avatar = null;
    
    // Reset avatar preview
    avatarPreview.value = null;
    if (avatarInputRef.value) {
        avatarInputRef.value.value = '';
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Appearance Settings">
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
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="grid gap-2">
                                <Label for="primary_color">Primary Color</Label>
                                <div class="flex items-center gap-2">
                                    <color-picker 
                                        v-model:pureColor="appearanceForm.primary_color"
                                        format="hex"
                                        shape="circle"
                                        :pickerType="['chrome']"
                                        useType="pure"
                                    />
                                </div>
                                <p v-if="appearanceForm.errors.primary_color" class="text-sm text-destructive">
                                    {{ appearanceForm.errors.primary_color }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Used for buttons and accents
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="chat_bubble_color">Chat Bubble Color</Label>
                                <div class="flex items-center gap-2">
                                    <color-picker 
                                        v-model:pureColor="appearanceForm.chat_bubble_color"
                                        format="hex"
                                        shape="circle"
                                        :pickerType="['chrome']"
                                        useType="pure"
                                    />
                                </div>
                                <p v-if="appearanceForm.errors.chat_bubble_color" class="text-sm text-destructive">
                                    {{ appearanceForm.errors.chat_bubble_color }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Background color of chat bubbles
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="chat_text_color">Chat Text Color</Label>
                                <div class="flex items-center gap-2">
                                    <color-picker 
                                        v-model:pureColor="appearanceForm.chat_text_color"
                                        format="hex"
                                        shape="circle"
                                        :pickerType="['chrome']"
                                        useType="pure"
                                    />
                                </div>
                                <p v-if="appearanceForm.errors.chat_text_color" class="text-sm text-destructive">
                                    {{ appearanceForm.errors.chat_text_color }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Text color in chat bubbles
                                </p>
                            </div>
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
    </AgentLayout>
</template>
