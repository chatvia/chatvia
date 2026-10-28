<script setup lang="ts">
/**
 * Live preview of the form being edited, drawn as the chat widget's window
 * shows it: the agent's header, a short exchange, the form card under the
 * assistant's reply, the composer and the "Powered by" credit. The look copies
 * the widget's own styles (the Shakomako repository's css/) and the agent's
 * brand colour, logo, avatar and light/dark theme. Submitting checks the
 * required fields and shows the success message, without saving anything.
 */
import { type FormDefinition, type FormField, previewFields } from '@/dashboard/agents/components/forms/formDefinition';
import { nearestShade } from '@/shared/lib/colorShades';
import { Copy, EllipsisVertical, MessageSquare, Mic, Moon, SendHorizontal, Sun, ThumbsDown, ThumbsUp, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type FieldValue = string | boolean | string[];

const props = defineProps<{
    form: FormDefinition;
    appearance: Domain.Form.Data.FormPreviewAppearanceData;
}>();

const { t } = useI18n();

const fields = computed(() => previewFields(props.form));

// The dashboard's dark theme re-maps every palette colour, but the widget
// always uses the light palette, so the brand colour comes from the light
// `:root` rule (the `var()` is the fallback when no stylesheet can be read).
function lightPaletteColor(property: string): string | null {
    const search = (rules: CSSRuleList): string | null => {
        for (const rule of Array.from(rules)) {
            if (rule instanceof CSSStyleRule && rule.selectorText === ':root') {
                const value = rule.style.getPropertyValue(property).trim();
                if (value) return value;
            } else if ('cssRules' in rule) {
                const value = search((rule as CSSGroupingRule).cssRules);
                if (value) return value;
            }
        }
        return null;
    };

    for (const sheet of Array.from(document.styleSheets)) {
        try {
            const value = search(sheet.cssRules);
            if (value) return value;
        } catch {
            // A cross-origin stylesheet cannot be read.
        }
    }
    return null;
}

const primary = computed(() => {
    const property = `--${props.appearance.color_theme}-${nearestShade(props.appearance.color_shade)}`;
    return lightPaletteColor(property) ?? `var(${property})`;
});

const values = reactive<Record<string, FieldValue>>({});
const errors = reactive<Record<string, boolean>>({});
const submitted = ref(false);

// Editing the form brings the preview back from the success view.
watch(
    () => props.form,
    () => {
        submitted.value = false;
    },
    { deep: true },
);

const isChoiceGroup = (field: FormField): boolean => field.type === 'radio' || field.type === 'multicheckbox';
const isTextInput = (field: FormField): boolean => ['text', 'email', 'tel', 'number', 'date'].includes(field.type);

function isEmpty(value: FieldValue | undefined): boolean {
    return value === undefined || value === '' || value === false || (Array.isArray(value) && value.length === 0);
}

function onSubmit(): void {
    let valid = true;
    for (const field of fields.value) {
        errors[field.key] = field.required && isEmpty(values[field.key]);
        if (errors[field.key]) valid = false;
    }
    if (valid) submitted.value = true;
}

function showFormAgain(): void {
    for (const key of Object.keys(values)) delete values[key];
    for (const key of Object.keys(errors)) delete errors[key];
    submitted.value = false;
}

function selected(key: string): string[] {
    const value = values[key];
    return Array.isArray(value) ? value : [];
}

function toggleOption(key: string, option: string, checked: boolean): void {
    values[key] = checked ? [...selected(key), option] : selected(key).filter((value) => value !== option);
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex min-h-8 items-center justify-between gap-2">
            <h2 class="text-base font-semibold">{{ t('agents.forms.preview') }}</h2>
            <button v-if="submitted" type="button" class="text-primary text-sm font-medium hover:underline" @click="showFormAgain">
                {{ t('agents.forms.preview_show_form_again') }}
            </button>
        </div>

        <div class="form-chat-preview" :class="`form-chat-preview--${appearance.theme}`" :style="{ '--chat-primary': primary }">
            <div class="fcp-header" aria-hidden="true">
                <img v-if="appearance.logo_url" :src="appearance.logo_url" alt="" class="fcp-header-logo" referrerpolicy="no-referrer" />
                <span v-else class="fcp-header-logo"><MessageSquare class="size-8 -scale-x-100" fill="currentColor" :stroke-width="0" /></span>
                <div class="fcp-header-titlewrap">
                    <div class="fcp-header-title">{{ appearance.title }}</div>
                    <div class="fcp-header-subtitle">{{ t('agents.forms.preview_ai_label') }}</div>
                </div>
                <span class="fcp-header-btn"><Moon class="fcp-icon-light size-[18px]" /><Sun class="fcp-icon-dark size-[18px]" /></span>
                <span class="fcp-header-btn"><EllipsisVertical class="size-[18px]" /></span>
                <span class="fcp-header-btn"><X class="size-4" :stroke-width="2.5" /></span>
            </div>

            <div class="fcp-messages">
                <div class="fcp-message fcp-message--user" aria-hidden="true">
                    <div class="fcp-bubble fcp-bubble--user">{{ t('agents.forms.preview_user_message') }}</div>
                    <div class="fcp-message-time">{{ t('shared.time_ago.just_now') }}</div>
                </div>

                <div class="fcp-message">
                    <div class="fcp-content">
                        <div v-if="appearance.avatar_url" class="fcp-avatar" aria-hidden="true">
                            <img :src="appearance.avatar_url" alt="" referrerpolicy="no-referrer" />
                        </div>
                        <div class="fcp-bubble fcp-bubble--assistant">{{ t('agents.forms.preview_intro') }}</div>

                        <div class="fcp-form">
                            <div v-if="submitted" class="fcp-form-success">
                                <span class="fcp-form-check">✓</span>
                                <span>{{ form.success_message || t('chat.forms.default_success') }}</span>
                            </div>

                            <template v-else>
                                <div v-if="form.name || form.subtitle" class="fcp-form-header">
                                    <div v-if="form.name" class="fcp-form-title">{{ form.name }}</div>
                                    <div v-if="form.subtitle" class="fcp-form-subtitle">{{ form.subtitle }}</div>
                                </div>

                                <form class="fcp-form-body" novalidate @submit.prevent="onSubmit">
                                    <div v-for="field in fields" :key="field.key" class="fcp-form-field">
                                        <label v-if="field.type === 'checkbox'" class="fcp-form-choice">
                                            <input v-model="values[field.key]" type="checkbox" />
                                            <span>{{ field.label }}<span v-if="field.required" class="fcp-form-req"> *</span></span>
                                        </label>

                                        <template v-else>
                                            <span class="fcp-form-label">
                                                {{ field.label }}<span v-if="field.required" class="fcp-form-req"> *</span>
                                            </span>

                                            <textarea
                                                v-if="field.type === 'textarea'"
                                                v-model="values[field.key] as string"
                                                class="fcp-form-input"
                                                rows="3"
                                                :placeholder="field.placeholder"
                                            />
                                            <select v-else-if="field.type === 'select'" v-model="values[field.key]" class="fcp-form-input">
                                                <option value="">{{ field.placeholder || '—' }}</option>
                                                <option v-for="option in field.options ?? []" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                            <div v-else-if="isChoiceGroup(field)" class="fcp-form-group" role="group">
                                                <label v-for="option in field.options ?? []" :key="option.value" class="fcp-form-choice">
                                                    <input
                                                        v-if="field.type === 'radio'"
                                                        v-model="values[field.key]"
                                                        type="radio"
                                                        :name="field.key"
                                                        :value="option.value"
                                                    />
                                                    <input
                                                        v-else
                                                        type="checkbox"
                                                        :checked="selected(field.key).includes(option.value)"
                                                        @change="toggleOption(field.key, option.value, ($event.target as HTMLInputElement).checked)"
                                                    />
                                                    <span>{{ option.label }}</span>
                                                </label>
                                            </div>
                                            <input
                                                v-else
                                                v-model="values[field.key]"
                                                class="fcp-form-input"
                                                :type="isTextInput(field) ? field.type : 'text'"
                                                :placeholder="field.placeholder"
                                            />
                                        </template>

                                        <div v-if="errors[field.key]" class="fcp-form-error">{{ t('agents.forms.preview_required') }}</div>
                                    </div>

                                    <button type="submit" class="fcp-form-submit">{{ t('chat.forms.submit') }}</button>
                                </form>
                            </template>
                        </div>
                    </div>

                    <div class="fcp-actions" :class="{ 'fcp-actions--indented': appearance.avatar_url }" aria-hidden="true">
                        <span class="fcp-action"><Copy /></span>
                        <span class="fcp-action"><ThumbsUp /></span>
                        <span class="fcp-action"><ThumbsDown /></span>
                        <span class="fcp-actions-time">{{ t('shared.time_ago.just_now') }}</span>
                    </div>
                </div>
            </div>

            <div class="fcp-composer" aria-hidden="true">
                <span class="fcp-composer-placeholder">{{ appearance.composer_placeholder || t('composer.default_placeholder') }}</span>
                <span v-if="appearance.voice_input" class="fcp-composer-mic"><Mic /></span>
                <span class="fcp-composer-send"><SendHorizontal fill="currentColor" :stroke-width="0" /></span>
            </div>

            <div v-if="appearance.show_branding" class="fcp-footer" aria-hidden="true">
                <img src="/images/logo.svg" alt="" class="fcp-footer-logo" />
                <span>{{ t('agents.forms.preview_powered_by') }}</span>
            </div>
        </div>
    </div>
</template>

<!-- Not scoped: the dark tokens key off the dashboard's `.dark` root class.
     Every rule sits under `.form-chat-preview`, and the values copy the chat
     widget's (Shakomako css/shared.css + css/widget.css). -->
<style>
.form-chat-preview {
    --chat-primary-contrast: #ffffff;
    --chat-header-text: #ffffff;
    --chat-bg: #ffffff;
    --chat-surface: #ffffff;
    --chat-text: #1e293b;
    --chat-text-secondary: #475569;
    --chat-text-muted: #64748b;
    --chat-border: rgba(0, 0, 0, 0.06);
    --chat-border-strong: rgba(0, 0, 0, 0.1);
    --chat-divider: rgba(0, 0, 0, 0.1);
    --chat-user-text: #ffffff;
    --chat-assistant-bubble: #f4f4f5;
    --chat-assistant-text: #1e293b;
    --chat-assistant-border: rgba(0, 0, 0, 0.045);
    --chat-input-bg: #f7f8fa;
    --chat-input-bg-focus: #ffffff;
    --chat-shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.07);
    --chat-shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px -1px rgba(15, 23, 42, 0.06);
    --chat-shadow-xl: 0 24px 60px -16px rgba(15, 23, 42, 0.22), 0 12px 30px -12px rgba(15, 23, 42, 0.13);
    --chat-ring-hairline: 0 0 0 1px rgba(15, 23, 42, 0.06);
    --chat-shadow-accent-sm: 0 2px 8px -2px color-mix(in srgb, var(--chat-primary) 36%, transparent);

    display: flex;
    flex-direction: column;
    /* At least the widget's window height; taller when the form needs it, so
       the whole form shows without scrolling inside the window. */
    min-height: clamp(600px, calc(100dvh - 8rem), 850px);
    overflow: hidden;
    font-family:
        'Inter',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: var(--chat-text);
    background-color: var(--chat-bg);
    border-radius: 20px;
    box-shadow: var(--chat-shadow-xl), var(--chat-ring-hairline);
    -webkit-font-smoothing: antialiased;
}

.form-chat-preview--dark,
.dark .form-chat-preview--auto {
    --chat-bg: #0f172a;
    --chat-surface: #1e293b;
    --chat-text: #e5e9f0;
    --chat-text-secondary: #9aa7bd;
    --chat-text-muted: #7c8aa3;
    --chat-border: rgba(255, 255, 255, 0.09);
    --chat-border-strong: rgba(255, 255, 255, 0.16);
    --chat-divider: rgba(255, 255, 255, 0.16);
    --chat-assistant-bubble: #1e293b;
    --chat-assistant-text: #e5e9f0;
    --chat-assistant-border: rgba(255, 255, 255, 0.07);
    --chat-input-bg: #1b2638;
    --chat-input-bg-focus: #223047;
    --chat-shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.4);
    --chat-shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.5), 0 1px 2px -1px rgba(0, 0, 0, 0.4);
    --chat-shadow-xl: 0 24px 60px -16px rgba(0, 0, 0, 0.7), 0 12px 30px -12px rgba(0, 0, 0, 0.5);
    --chat-ring-hairline: 0 0 0 1px rgba(255, 255, 255, 0.08);
}

/* The header's theme button shows the moon in light mode, the sun in dark. */
.form-chat-preview .fcp-icon-dark,
.form-chat-preview--dark .fcp-icon-light,
.dark .form-chat-preview--auto .fcp-icon-light {
    display: none;
}

.form-chat-preview--dark .fcp-icon-dark,
.dark .form-chat-preview--auto .fcp-icon-dark {
    display: block;
}

.form-chat-preview .fcp-header {
    position: relative;
    z-index: 2;
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 2px;
    padding-block: 12px;
    padding-inline: 16px 12px;
    color: var(--chat-header-text);
    background: linear-gradient(
        165deg,
        color-mix(in srgb, var(--chat-primary) 96%, white) 0%,
        var(--chat-primary) 46%,
        color-mix(in srgb, var(--chat-primary) 90%, black) 100%
    );
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.14) inset,
        0 2px 10px -4px color-mix(in srgb, var(--chat-primary) 42%, transparent);
}

.form-chat-preview .fcp-header-logo {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    margin-inline-end: 10px;
    object-fit: cover;
    border-radius: 10px;
}

.form-chat-preview .fcp-header-titlewrap {
    display: flex;
    flex-grow: 1;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
    min-width: 0;
}

.form-chat-preview .fcp-header-title {
    overflow: hidden;
    font-size: 16px;
    font-weight: 600;
    line-height: 1.25;
    letter-spacing: -0.01em;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.form-chat-preview .fcp-header-subtitle {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    line-height: 1.2;
    letter-spacing: 0.005em;
    opacity: 0.8;
}

.form-chat-preview .fcp-header-subtitle::before {
    content: '';
    flex-shrink: 0;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: currentColor;
    opacity: 0.9;
    box-shadow: 0 0 0 3px color-mix(in srgb, currentColor 22%, transparent);
}

.form-chat-preview .fcp-header-btn {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 10px;
}

.form-chat-preview .fcp-messages {
    flex: 1;
    padding: 16px;
    background: var(--chat-bg);
}

.form-chat-preview .fcp-message {
    margin-block: 10px 12px;
    color: var(--chat-text-muted);
}

.form-chat-preview .fcp-message--user {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    padding-inline-end: 8px;
}

.form-chat-preview .fcp-content {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    width: 100%;
}

.form-chat-preview .fcp-avatar {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    margin-inline-end: 8px;
    overflow: hidden;
    border-radius: 50%;
}

.form-chat-preview .fcp-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.form-chat-preview .fcp-bubble {
    min-width: 0;
    font-size: 14px;
    line-height: 1.65;
    letter-spacing: -0.01em;
    overflow-wrap: break-word;
    border-radius: 16px;
}

.form-chat-preview .fcp-bubble--user {
    max-width: 85%;
    padding: 12px 16px;
    color: var(--chat-user-text);
    background: linear-gradient(135deg, var(--chat-primary) 0%, color-mix(in srgb, var(--chat-primary) 88%, black) 100%);
    border-end-end-radius: 6px;
    box-shadow: var(--chat-shadow-accent-sm);
}

.form-chat-preview .fcp-bubble--assistant {
    max-width: calc(100% - 36px);
    padding: 11px 14px;
    color: var(--chat-assistant-text);
    background-color: var(--chat-assistant-bubble);
    border: 1px solid var(--chat-assistant-border);
    border-end-start-radius: 6px;
    box-shadow: var(--chat-shadow-xs);
}

.form-chat-preview .fcp-message-time {
    margin-top: 4px;
    margin-inline-end: 4px;
    font-size: 11px;
    white-space: nowrap;
}

.form-chat-preview .fcp-actions {
    display: flex;
    align-items: center;
    gap: 2px;
    margin-top: 6px;
    opacity: 0.85;
}

.form-chat-preview .fcp-actions--indented {
    margin-inline-start: 36px;
}

.form-chat-preview .fcp-action {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    color: var(--chat-text-muted);
    border-radius: 6px;
}

.form-chat-preview .fcp-action svg {
    width: 13px;
    height: 13px;
}

.form-chat-preview .fcp-actions-time {
    margin-inline-start: 4px;
    padding-inline-start: 8px;
    font-size: 11px;
    white-space: nowrap;
    border-inline-start: 1px solid var(--chat-divider);
}

.form-chat-preview .fcp-form {
    flex: 0 0 100%;
    width: 100%;
    margin-top: 8px;
    padding: 16px;
    color: var(--chat-text);
    background-color: var(--chat-surface);
    border: 1px solid var(--chat-border);
    border-radius: 10px;
}

.form-chat-preview .fcp-form-header {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--chat-border);
}

.form-chat-preview .fcp-form-title {
    font-size: 16px;
    font-weight: 600;
    line-height: 1.3;
}

.form-chat-preview .fcp-form-subtitle {
    margin-top: 4px;
    font-size: 13px;
    line-height: 1.45;
    color: var(--chat-text-secondary);
}

.form-chat-preview .fcp-form-body,
.form-chat-preview .fcp-form-field,
.form-chat-preview .fcp-form-group {
    display: flex;
    flex-direction: column;
}

.form-chat-preview .fcp-form-body {
    gap: 12px;
}

.form-chat-preview .fcp-form-field,
.form-chat-preview .fcp-form-group {
    gap: 4px;
}

.form-chat-preview .fcp-form-label {
    font-size: 12px;
    color: var(--chat-text-secondary);
}

.form-chat-preview .fcp-form-req,
.form-chat-preview .fcp-form-error {
    color: #dc2626;
}

.form-chat-preview .fcp-form-error {
    font-size: 12px;
}

.form-chat-preview .fcp-form-input {
    width: 100%;
    padding: 8px 10px;
    font-family: inherit;
    font-size: 13px;
    color: var(--chat-text);
    background-color: var(--chat-input-bg);
    border: 1px solid var(--chat-border);
    border-radius: 6px;
    transition: border-color 0.15s ease;
}

.form-chat-preview .fcp-form-input:focus {
    outline: none;
    background-color: var(--chat-input-bg-focus);
    border-color: var(--chat-primary);
}

.form-chat-preview textarea.fcp-form-input {
    min-height: 60px;
    resize: vertical;
}

.form-chat-preview .fcp-form-choice {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    cursor: pointer;
}

.form-chat-preview .fcp-form-choice input {
    accent-color: var(--chat-primary);
}

.form-chat-preview .fcp-form-submit {
    align-self: flex-start;
    padding: 8px 16px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    color: var(--chat-primary-contrast);
    background-color: var(--chat-primary);
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: opacity 0.15s ease;
}

.form-chat-preview .fcp-form-submit:hover {
    opacity: 0.9;
}

.form-chat-preview .fcp-form-success {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #16a34a;
}

.form-chat-preview .fcp-form-check {
    font-weight: 700;
}

.form-chat-preview .fcp-composer {
    display: flex;
    flex-shrink: 0;
    align-items: flex-end;
    gap: 6px;
    margin: 10px 16px 14px;
    padding: 8px 10px;
    padding-inline-start: 16px;
    background: var(--chat-bg);
    border: 1px solid var(--chat-border-strong);
    border-radius: 26px;
    box-shadow: var(--chat-shadow-sm);
}

.form-chat-preview .fcp-composer-placeholder {
    flex: 1 1 auto;
    min-width: 0;
    padding-block: 7px;
    overflow: hidden;
    font-size: 16px;
    line-height: 1.375;
    color: var(--chat-text-muted);
    white-space: nowrap;
    text-overflow: ellipsis;
}

.form-chat-preview .fcp-composer-mic,
.form-chat-preview .fcp-composer-send {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.form-chat-preview .fcp-composer-mic {
    width: 34px;
    height: 34px;
    color: var(--chat-text-muted);
}

.form-chat-preview .fcp-composer-mic svg {
    width: 18px;
    height: 18px;
}

.form-chat-preview .fcp-composer-send {
    width: 36px;
    height: 36px;
    color: var(--chat-text-muted);
    background-color: color-mix(in srgb, var(--chat-text) 7%, transparent);
}

.form-chat-preview .fcp-composer-send svg {
    width: 16px;
    height: 16px;
}

.form-chat-preview .fcp-footer {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    padding: 2px 14px 10px;
    font-size: 11.5px;
    font-weight: 500;
    color: var(--chat-text-muted);
    opacity: 0.85;
}

.form-chat-preview .fcp-footer-logo {
    width: 16px;
    height: 16px;
    margin-inline-end: 6px;
    object-fit: contain;
}
</style>
