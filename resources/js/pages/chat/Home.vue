<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import axios from "axios";
import {Head, router} from '@inertiajs/vue3';
import AppLayout from "@/components/chat/ChatLayout.vue";
import Composer from "@/components/chat/Composer.vue";
import { useColorTheme } from '@/composables/useColorTheme';
import AppLogoIcon from "@/components/AppLogoIcon.vue";

const props = defineProps({
    appearance: {
        type: Object,
        default: () => ({
            color_theme: 'black',
            welcome_message: null,
            chat_window_title: null,
            chat_footer_text: null,
            avatar: null,
            logo: null,
            composer_placeholder: null
        })
    }
});

const selectedModel = ref();
const selectedStarter = ref('');

const starters = ref([]);

// Usage checking
const USAGE_CHECK_INTERVAL = 60000; // 1 minute
const canSendMessages = ref(true);
const usageCheckInterval = ref(null);

const { applyColorTheme } = useColorTheme();

// Function to check usage status
const checkUsage = async () => {
    try {
        const response = await axios.get(route('chat.usage'));
        canSendMessages.value = response.data.can_send_messages;
    } catch (error) {
        console.error('Failed to check usage:', error);
        // On error, allow sending messages to prevent blocking the user
        canSendMessages.value = true;
    }
};

// Start periodic usage checking
const startUsageChecking = () => {
    // Check immediately
    checkUsage();

    // Then check at regular intervals
    usageCheckInterval.value = setInterval(checkUsage, USAGE_CHECK_INTERVAL);
};

// Stop periodic usage checking
const stopUsageChecking = () => {
    if (usageCheckInterval.value) {
        clearInterval(usageCheckInterval.value);
        usageCheckInterval.value = null;
    }
};

onMounted(() => {
    // Start usage checking
    startUsageChecking();

    // Apply color theme
    applyColorTheme(props.appearance);

    // Fetch starters
    axios.get(route('chat.starters')).then(response => {
        starters.value = response.data;
    });
});

onUnmounted(() => {
    // Stop usage checking
    stopUsageChecking();
});

function updateSelectedModel(newModel) {
  selectedModel.value = newModel;
  localStorage.setItem('selectedModel', newModel.id);
}

async function createNewConversation(message, model) {
  await axios.post(route('conversations.create'), { message: message, model: model.id }).then(response => {
    router.visit(route('chat.show', { conversationId: response.data.id, model: model.id, new: '1' }));
  });
}

function handleStarterClick(starter) {
    selectedStarter.value = starter.text;
}
</script>

<template>
  <AppLayout>
      <Head title="ChatVia" />
            <template #content>
          <div class="flex h-screen items-center justify-center px-4">
              <div class="w-full max-w-4xl mx-auto">
                  <!-- Logo -->
                  <div class="flex justify-center mb-6">
                      <div class="h-20 w-20 overflow-hidden">
                          <img v-if="props.appearance.logo"
                              :src="props.appearance.logo"
                              alt="Logo"
                              class="h-full w-full object-cover text-red"
                          />
                          <AppLogoIcon v-else class="h-10" />
                      </div>
                  </div>

                  <!-- Dynamic welcome message -->
                  <h1 class="mb-8 text-center text-4xl font-medium text-gray-800 dark:text-white">
                      {{ props.appearance?.welcome_message }}
                  </h1>

                  <!-- Conversation Starters -->
                  <div class="mb-8 px-2 sm:px-4">
                      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8 justify-items-center max-w-5xl mx-auto">
                          <button
                              v-for="starter in starters"
                              :key="starter.id"
                              @click="handleStarterClick(starter)"
                              class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-50 via-white to-slate-50 dark:from-slate-800/50 dark:via-slate-700/30 dark:to-slate-800/50 p-4 text-left border border-slate-200/60 dark:border-slate-600/30 hover:border-slate-300/80 dark:hover:border-slate-500/50 transition-all duration-300 ease-out hover:shadow-xl hover:shadow-slate-200/25 dark:hover:shadow-slate-900/25 hover:-translate-y-1 active:translate-y-0 active:scale-[0.99] backdrop-blur-sm w-full"
                          >
                              <div class="relative z-10">
                                  <p class="text-sm font-medium text-slate-700 dark:text-slate-200 leading-relaxed tracking-wide">
                                      {{ starter.text }}
                                  </p>
                              </div>

                              <!-- Modern gradient overlay -->
                              <div class="absolute inset-0 bg-gradient-to-br from-blue-50/60 via-transparent to-purple-50/40 dark:from-blue-500/5 dark:via-transparent dark:to-purple-500/5 opacity-0 group-hover:opacity-100 transition-all duration-300 ease-out"></div>

                              <!-- Subtle shine effect -->
                              <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 dark:via-white/5 to-transparent translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-700 ease-out"></div>
                          </button>
                      </div>
                  </div>

                  <div class="mx-auto w-full px-2 sm:px-4">
                      <!-- Show composer if user can send messages -->
                      <Composer
                          v-if="canSendMessages"
                          :text="selectedStarter"
                          :placeholder="appearance.composer_placeholder"
                          @modelSelected="updateSelectedModel"
                          @messageAdded="createNewConversation"
                      />

                      <!-- Show limit message if user cannot send messages -->
                      <div v-else class="flex items-center justify-center p-4 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg max-w-md mx-auto">
                          <div class="text-center">
                              <p class="text-sm font-medium text-orange-800 dark:text-orange-200">
                                  You've reached your message limit
                              </p>
                              <p class="text-xs text-orange-600 dark:text-orange-300 mt-1">
                                  Please upgrade your plan to continue chatting
                              </p>
                          </div>
                      </div>

                      <!-- Dynamic footer text -->
                      <div v-if="props.appearance?.chat_footer_text" class="text-center mt-4">
                          <p class="text-xs text-muted-foreground">
                              {{ props.appearance.chat_footer_text }}
                          </p>
                      </div>
                  </div>
              </div>
          </div>
      </template>

  </AppLayout>
</template>
