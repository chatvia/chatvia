<script setup>
import ConfirmationDialog from '@/components/chat/ConfirmationDialog.vue';
import WaitingIndicator from '@/components/chat/WaitingIndicator.vue';
import { usePage } from '@inertiajs/vue3';
import { vOnClickOutside } from '@vueuse/components';
import hljs from 'highlight.js';
import { Marked } from 'marked';
import markedAlert from 'marked-alert';
import markedCodePreview from 'marked-code-preview';
import { markedHighlight } from 'marked-highlight';
import markedKatex from 'marked-katex-extension';
import { nextTick, onMounted, ref, watch } from 'vue';

const messageFeedback = ref({});
const isExportDropdownOpen = ref({});
const copiedMessageId = ref(null);
const isEditing = ref(false);
const editedContent = ref('');
const page = usePage();

const emit = defineEmits(['messageUpdated']);

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
    isThinking: {
        type: Boolean,
        default: false,
    },
    hideFeedback: {
        type: Boolean,
        default: false,
    },
    isChatExpanded: {
        type: Boolean,
        default: false,
    },
});
const isChatExpanded = defineModel();
const messageRef = ref(props.message);

const marked = new Marked(
    markedHighlight({
        emptyLangClass: 'hljs',
        langPrefix: 'hljs language-',
        highlight(code, lang, info) {
            const language = hljs.getLanguage(lang) ? lang : 'plaintext';
            return hljs.highlight(code, { language }).value;
        },
    }),
);

const availableModels = ref(page.props.models);
const findModelIdx = (modelId) => {
    return availableModels.value.find((m) => m.external_id === modelId) || page.props.defaultModel;
};

function formatMessage(content) {
    return marked.use({ gfm: true }).use(markedKatex()).use(markedCodePreview()).use(markedAlert()).parse(content);
}

function provideFeedback(messageId, type) {
    if (messageFeedback.value[messageId] === type) {
        delete messageFeedback.value[messageId];
    } else {
        messageFeedback.value[messageId] = type;
    }
}

const messageContentRef = ref();

function copyMessageToClipboard(content, id) {
    // Use the Clipboard API if available
    if (navigator.clipboard) {
        navigator.clipboard
            .writeText(content)
            .then(() => {
                copiedMessageId.value = id;
                // Reset after 2 seconds
                setTimeout(() => {
                    if (copiedMessageId.value === id) {
                        copiedMessageId.value = null;
                    }
                }, 2000);
            })
            .catch((err) => {
                console.error('Failed to copy text: ', err);
                fallbackCopyToClipboard(content, id);
            });
    } else {
        fallbackCopyToClipboard(content, id);
    }
}

function fallbackCopyToClipboard(content, id) {
    try {
        const tempTextarea = document.createElement('textarea');
        tempTextarea.value = content;
        document.body.appendChild(tempTextarea);
        tempTextarea.select();
        document.execCommand('copy');
        document.body.removeChild(tempTextarea);

        copiedMessageId.value = id;
        setTimeout(() => {
            if (copiedMessageId.value === id) {
                copiedMessageId.value = null;
            }
        }, 2000);
    } catch (err) {
        console.error('Failed to copy text with fallback: ', err);
    }
}

function toggleExportDropdown(messageId, event) {
    // Stop event propagation
    if (event) event.stopPropagation();

    // Close all other export dropdowns
    const newState = {};
    if (!isExportDropdownOpen.value[messageId]) {
        newState[messageId] = true;
    }
    isExportDropdownOpen.value = newState;
}

const closeMenu = () => {
    isExportDropdownOpen.value = false;
};
watch(
    () => props.message.content,
    () => {
        if (props.message.content) {
            nextTick(() => {
                setupCodeBlockCopyButtons();
            });
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (props.message.content) {
        setupCodeBlockCopyButtons();
    }
});

function startEditing() {
    if (props.message.role === 'user') {
        editedContent.value = props.message.content;
        isEditing.value = true;

        // Focus the textarea after the DOM updates
        nextTick(() => {
            const textareaElement = document.getElementById(`edit-message-${props.message.id}`);
            if (textareaElement) {
                textareaElement.focus();
                textareaElement.select();
            }
        });
    }
}

function cancelEditing() {
    isEditing.value = false;
    editedContent.value = '';
}

function saveEditedMessage() {
    if (!editedContent.value.trim()) return;

    try {
        const newMessage = editedContent.value.trim();
        // messageRef.value.content = newMessage
        emit('messageUpdated', props.message.id, newMessage);
        isEditing.value = false;
    } catch (error) {
        console.error('Failed to update message:', error);
    }
}

function handleEditKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        saveEditedMessage();
    } else if (event.key === 'Escape') {
        cancelEditing();
    }
}

function setupCodeBlockCopyButtons() {
    if (!messageContentRef.value) return;

    const codeBlocks = messageContentRef.value.querySelectorAll('pre');
    codeBlocks.forEach((pre) => {
        // Check if we already added a copy button to this pre
        if (pre.querySelector('.code-copy-button')) return;

        // Create the copy button
        const copyButton = document.createElement('button');
        copyButton.className = 'code-copy-button';
        copyButton.setAttribute('aria-label', 'Copy code to clipboard');
        copyButton.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
        `;

        // Add click event to copy code
        copyButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const code = pre.querySelector('code');

            if (code) {
                navigator.clipboard
                    .writeText(code.textContent || '')
                    .then(() => {
                        copyButton.classList.add('copied');
                        copyButton.innerHTML = `
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        `;

                        // Reset after 2 seconds
                        setTimeout(() => {
                            copyButton.classList.remove('copied');
                            copyButton.innerHTML = `
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            `;
                        }, 2000);
                    })
                    .catch((err) => console.error('Failed to copy code: ', err));
            }
        });

        // Add the button to the pre element
        pre.style.position = 'relative';
        pre.appendChild(copyButton);
    });
}

const closeExportAsMenu = () => {
    isExportDropdownOpen.value = false;
};

const deleteMessage = () => {};
</script>

<template>
    <div
        :class="[message.role === 'user' ? 'flex-row-reverse justify-end' : '', !isChatExpanded ? 'max-w-3xl' : '']"
        class="mx-auto flex items-start gap-4 sm:gap-6"
    >
        <div :class="message.role === 'user' ? 'flex justify-end' : ''" class="prose dark:prose-invert max-w-none flex-1">
            <div
                :class="[
                    message.role === 'user'
                        ? 'group relative inline-block max-w-xl rounded-2xl bg-gray-800 px-4 py-2 text-base font-normal text-white shadow-sm'
                        : 'mx-auto my-8 block px-2 text-lg leading-relaxed text-gray-800 dark:text-white',
                ]"
            >
                <div v-if="isEditing && message.role === 'user'" class="w-full">
                    <textarea
                        :id="`edit-message-${message.id}`"
                        v-model="editedContent"
                        class="min-h-[100px] w-full resize-none rounded-lg border-0 bg-transparent p-2 text-white focus:border-0 focus:ring-0 focus:outline-none"
                        @keydown="handleEditKeyDown"
                    ></textarea>

                    <div class="mt-2 flex justify-end space-x-2">
                        <button
                            class="cursor-pointer rounded-md bg-gray-700 px-3 py-1 text-xs text-white transition-colors hover:bg-gray-600"
                            @click="cancelEditing"
                        >
                            Cancel
                        </button>
                        <button
                            class="cursor-pointer rounded-md bg-indigo-600 px-3 py-1 text-xs text-white transition-colors hover:bg-indigo-500"
                            @click="saveEditedMessage"
                        >
                            Send
                        </button>
                    </div>
                </div>
                <div v-else>
                    <div v-if="messageRef.content" id="chat-ai-responses" ref="messageContentRef" v-html="formatMessage(message.content)"></div>

                    <div v-else>
                        <WaitingIndicator />
                    </div>
                </div>

                <div
                    v-if="message.role === 'assistant' && message.content && !hideFeedback"
                    class="mt-3 flex items-center justify-between border-t border-gray-200/10 pt-2 dark:border-gray-700/20"
                >
                    <div class="flex items-center space-x-1.5">
                        <button
                            aria-label="Copy message"
                            class="group relative cursor-pointer rounded-full p-1.5 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                            @click="copyMessageToClipboard(message.content, message.id)"
                        >
                            <svg
                                :class="{ 'scale-75 opacity-0': copiedMessageId === message.id }"
                                class="h-5 w-5 transition-all duration-200"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            <!-- Checkmark that appears when copied -->
                            <svg
                                v-if="copiedMessageId === message.id"
                                class="animate-scale-in absolute top-1/2 left-1/2 h-5 w-5 -translate-x-1/2 -translate-y-1/2 text-green-400"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div
                                class="pointer-events-none absolute bottom-full left-1/2 mb-1 -translate-x-1/2 transform rounded bg-gray-800 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                Copy
                            </div>
                        </button>

                        <button
                            :class="[
                                messageFeedback[message.id] === 'thumbs-up'
                                    ? 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400'
                                    : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800',
                            ]"
                            aria-label="Thumbs up"
                            class="group relative cursor-pointer rounded-full p-1.5 transition-colors"
                            @click="provideFeedback(message.id, 'thumbs-up')"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            <!-- Tooltip -->
                            <div
                                class="pointer-events-none absolute bottom-full left-1/2 mb-1 -translate-x-1/2 transform rounded bg-gray-800 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                Helpful
                            </div>
                        </button>

                        <button
                            :class="[
                                messageFeedback[message.id] === 'thumbs-down'
                                    ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
                                    : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800',
                            ]"
                            aria-label="Thumbs down"
                            class="group relative cursor-pointer rounded-full p-1.5 transition-colors"
                            @click="provideFeedback(message.id, 'thumbs-down')"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018a2 2 0 01.485.06l3.76.94m-7 10v5a2 2 0 002 2h.096c.5 0 .905-.405.905-.904 0-.715.211-1.413.608-2.008L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            <!-- Tooltip -->
                            <div
                                class="pointer-events-none absolute bottom-full left-1/2 mb-1 -translate-x-1/2 transform rounded bg-gray-800 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                Not helpful
                            </div>
                        </button>

                        <!-- Delete message button -->
                        <ConfirmationDialog
                            cancelText="No, keep it"
                            confirmText="Yes, delete"
                            description="This action cannot be undone."
                            title="Delete this message?"
                            @cancel="() => {}"
                            @confirm="deleteMessage(message.id)"
                        >
                            <template #trigger>
                                <button
                                    aria-label="Delete message"
                                    class="group relative cursor-pointer rounded-full p-1.5 text-gray-500 transition-colors hover:bg-red-100 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        viewBox="0 0 24 24"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                    <!-- Tooltip -->
                                    <div
                                        class="pointer-events-none absolute bottom-full left-1/2 mb-1 -translate-x-1/2 transform rounded bg-gray-800 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        Delete the current response
                                    </div>
                                </button>
                            </template>
                        </ConfirmationDialog>

                        <div v-on-click-outside="closeExportAsMenu" class="dropdown-export-container relative">
                            <button
                                aria-label="Export message"
                                class="group relative rounded-full p-1.5 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                @click="toggleExportDropdown(message.id, $event)"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                                <!-- Tooltip -->
                                <div
                                    class="pointer-events-none absolute bottom-full left-1/2 mb-1 -translate-x-1/2 transform rounded bg-gray-800 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100"
                                >
                                    Export
                                </div>
                            </button>

                            <!-- Export Dropdown Menu -->
                            <div
                                v-if="isExportDropdownOpen[message.id]"
                                class="absolute bottom-full left-0 z-50 mb-2 w-40 cursor-pointer rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                                style="transform-origin: bottom left"
                            >
                                <!-- Dropdown arrow -->
                                <div
                                    class="absolute -bottom-2 left-4 h-4 w-4 rotate-45 transform border-r border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
                                ></div>

                                <div
                                    class="cursor-pointer border-b border-gray-100 px-3 py-2 text-xs font-medium text-gray-400 dark:border-gray-800 dark:text-gray-500"
                                >
                                    Export as
                                </div>

                                <button
                                    class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-gray-700 transition-all duration-200 hover:translate-x-0.5 hover:scale-[1.02] hover:bg-indigo-50 dark:text-gray-300 dark:hover:bg-indigo-900/30"
                                    @click="exportAsPdf(message.id)"
                                >
                                    <svg
                                        class="h-4 w-4 text-red-500"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        viewBox="0 0 24 24"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="16" x2="8" y1="13" y2="13"></line>
                                        <line x1="16" x2="8" y1="17" y2="17"></line>
                                        <polyline points="10 9 9 9 8 9"></polyline>
                                    </svg>
                                    <span>PDF</span>
                                </button>

                                <button
                                    class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-gray-700 transition-all duration-200 hover:translate-x-0.5 hover:scale-[1.02] hover:bg-indigo-50 dark:text-gray-300 dark:hover:bg-indigo-900/30"
                                    @click="exportAsMarkdown(message.id)"
                                >
                                    <svg
                                        class="h-4 w-4 text-blue-500"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        viewBox="0 0 24 24"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                        <line x1="9" x2="9" y1="10" y2="16"></line>
                                        <line x1="15" x2="15" y1="10" y2="16"></line>
                                        <line x1="9" x2="15" y1="13" y2="13"></line>
                                    </svg>
                                    <span>Markdown</span>
                                </button>

                                <button
                                    class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-gray-700 transition-all duration-200 hover:translate-x-0.5 hover:scale-[1.02] hover:bg-indigo-50 dark:text-gray-300 dark:hover:bg-indigo-900/30"
                                    @click="exportAsText(message.id)"
                                >
                                    <svg
                                        class="h-4 w-4 text-gray-500"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        viewBox="0 0 24 24"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                    </svg>
                                    <span>Text</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <div class="group relative">
                            <img :alt="findModelIdx(message.model).name" :src="'/images/llms/' + findModelIdx(message.model).icon" class="h-5 w-5" />
                            <div
                                class="pointer-events-none absolute right-0 bottom-full mb-1 transform rounded bg-gray-800 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                Generated by {{ findModelIdx(message.model).name }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="message.role === 'user' && message.content && !isEditing">
        <div class="mx-auto mt-2 flex max-w-3xl items-start justify-end gap-4 sm:gap-6">
            <div class="flex items-center space-x-1.5">
                <button
                    aria-label="Copy message"
                    class="group relative cursor-pointer rounded-full p-1.5 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                    @click="copyMessageToClipboard(message.content, message.id)"
                >
                    <svg
                        :class="{ 'scale-75 opacity-0': copiedMessageId === message.id }"
                        class="h w-4 transition-all duration-200"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <!-- Checkmark that appears when copied -->
                    <svg
                        v-if="copiedMessageId === message.id"
                        class="animate-scale-in absolute top-1/2 left-1/2 h-5 w-5 -translate-x-1/2 -translate-y-1/2 text-green-400"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div
                        class="pointer-events-none absolute bottom-full left-1/2 mb-1 -translate-x-1/2 transform rounded bg-gray-800 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        Copy
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.prose *) {
    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease;
    overflow-wrap: break-word;
    word-wrap: break-word;
    word-break: break-word;
    hyphens: auto;
}

/* Images */
:deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 0.375rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin: 0.5rem 0;
}

/* Code blocks - improved for mobile */
:deep(pre) {
    background-color: rgba(0, 0, 0, 0.03);
    border: 1px solid rgba(0, 0, 0, 0.05);
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
    margin: 1rem 0;
    padding: 1rem;
    border-radius: 0.375rem;
    overflow-x: auto;
    max-width: 100%;
    white-space: pre-wrap;
}

.dark :deep(pre) {
    background-color: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.05);
}

/* Code block content */
:deep(pre code) {
    display: block;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.875em;
    line-height: 1.7;
}

/* Inline code */
:deep(:not(pre) > code) {
    padding: 0.2em 0.4em;
    background-color: rgba(0, 0, 0, 0.03);
    border-radius: 3px;
    font-size: 0.9em;
    border: 1px solid rgba(0, 0, 0, 0.05);
    white-space: normal;
}

.dark :deep(:not(pre) > code) {
    background-color: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.05);
}

/* Blockquotes */
:deep(blockquote) {
    border-left: 3px solid #e5e7eb;
    padding-left: 1rem;
    font-style: italic;
    color: rgba(75, 85, 99);
    margin: 1rem 0;
}

.dark :deep(blockquote) {
    border-left-color: #4b5563;
    color: rgba(156, 163, 175);
}

/* Tables */
:deep(table) {
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 0.375rem;
    overflow-x: auto;
    display: block;
    max-width: 100%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin: 1rem 0;
}

:deep(th) {
    background-color: rgba(0, 0, 0, 0.03);
    font-weight: 600;
    text-align: left;
    padding: 0.75rem 1rem;
}

:deep(td) {
    padding: 0.75rem 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.dark :deep(th) {
    background-color: rgba(255, 255, 255, 0.03);
}

.dark :deep(td) {
    border-top-color: rgba(255, 255, 255, 0.05);
}

/* Links */
:deep(a) {
    transition:
        color 0.2s ease,
        text-decoration 0.2s ease;
}

:deep(a:hover) {
    text-decoration-thickness: 2px;
}

/* Lists */
:deep(ul),
:deep(ol) {
    padding-left: 1.5rem;
    margin: 0.75rem 0;
}

:deep(li) {
    margin: 0.375rem 0;
    padding-left: 0.25rem;
}

/* Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message-enter-active {
    animation: fadeIn 0.3s ease-out;
}

/* Headings */
:deep(h1),
:deep(h2),
:deep(h3),
:deep(h4),
:deep(h5),
:deep(h6) {
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    line-height: 1.3;
}

:deep(h1) {
    font-size: 1.5rem;
}

:deep(h2) {
    font-size: 1.3rem;
}

:deep(h3) {
    font-size: 1.15rem;
}

:deep(h4) {
    font-size: 1.05rem;
}

/* Mobile-specific adjustments */
@media (max-width: 640px) {
    :deep(pre) {
        font-size: 0.8em;
    }

    :deep(table) {
        font-size: 0.85em;
    }

    :deep(h1) {
        font-size: 1.3rem;
    }

    :deep(h2) {
        font-size: 1.15rem;
    }

    :deep(h3) {
        font-size: 1.05rem;
    }

    :deep(h4) {
        font-size: 1rem;
    }
}

/* Ensure message container doesn't overflow */
.prose {
    max-width: 100%;
    overflow-wrap: break-word;
}

/* Code block copy button */
:deep(.code-copy-button) {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    padding: 0.25rem;
    background-color: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 0.25rem;
    color: #6b7280;
    cursor: pointer;
    opacity: 0;
    transition:
        opacity 0.2s ease,
        background-color 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

:deep(pre:hover .code-copy-button) {
    opacity: 1;
}

:deep(.code-copy-button:hover) {
    background-color: rgba(255, 255, 255, 0.2);
    color: #4b5563;
}

:deep(.code-copy-button.copied) {
    background-color: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.dark :deep(.code-copy-button) {
    background-color: rgba(0, 0, 0, 0.2);
    color: #9ca3af;
}

.dark :deep(.code-copy-button:hover) {
    background-color: rgba(0, 0, 0, 0.3);
    color: #d1d5db;
}

.dark :deep(.code-copy-button.copied) {
    background-color: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.group:hover .edit-button {
    opacity: 1;
}

@media (max-width: 768px) {
    .edit-button {
        opacity: 1;
    }
}

@keyframes scaleIn {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    50% {
        transform: scale(1.2);
        opacity: 1;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.animate-scale-in {
    animation: scaleIn 0.3s ease-out forwards;
}

.animate-scale-in {
    animation: scaleIn 0.3s ease-out forwards;
}

/* GFM Alert Styling */
:deep(.markdown-alert) {
    padding: 0.75rem 1rem;
    margin: 1rem 0;
    border-left-width: 4px;
    border-left-style: solid;
    border-radius: 0.375rem;
}

/* Note alert styling */
:deep(.markdown-alert-note) {
    border-left-color: #3b82f6; /* blue-500 */
    background-color: rgba(59, 130, 246, 0.05);
}

:deep(.markdown-alert-note .markdown-alert-title) {
    color: #2563eb; /* blue-600 */
}

/* Tip alert styling */
:deep(.markdown-alert-tip) {
    border-left-color: #10b981; /* emerald-500 */
    background-color: rgba(16, 185, 129, 0.05);
}

:deep(.markdown-alert-tip .markdown-alert-title) {
    color: #059669; /* emerald-600 */
}

/* Important alert styling */
:deep(.markdown-alert-important) {
    border-left-color: #8b5cf6; /* violet-500 */
    background-color: rgba(139, 92, 246, 0.05);
}

:deep(.markdown-alert-important .markdown-alert-title) {
    color: #7c3aed; /* violet-600 */
}

/* Warning alert styling */
:deep(.markdown-alert-warning) {
    border-left-color: #f59e0b; /* amber-500 */
    background-color: rgba(245, 158, 11, 0.05);
}

:deep(.markdown-alert-warning .markdown-alert-title) {
    color: #d97706; /* amber-600 */
}

/* Caution alert styling */
:deep(.markdown-alert-caution) {
    border-left-color: #ef4444; /* red-500 */
    background-color: rgba(239, 68, 68, 0.05);
}

:deep(.markdown-alert-caution .markdown-alert-title) {
    color: #dc2626; /* red-600 */
}

:deep(.markdown-alert-title) {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

:deep(.markdown-alert-title svg) {
    width: 1.25rem;
    height: 1.25rem;
    flex-shrink: 0;
}
</style>
