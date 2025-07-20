<script setup>
import { onMounted, ref } from 'vue';
import axios from "axios";
import {Head, router} from '@inertiajs/vue3';
import AppLayout from "@/components/chat/ChatLayout.vue";
import Composer from "@/components/chat/Composer.vue";

const selectedModel = ref();
const selectedStarter = ref('');

const starters = ref([]);

// Add loading state
const isLoadingAppearance = ref(true);

// Add reactive ref for appearance
const appearance = ref({
    color_theme: 'black',
    welcome_message: null,
    chat_window_title: null,
    chat_footer_text: null,
    avatar: null
});

// Add function to fetch appearance
const fetchAppearance = async () => {
    try {
        const response = await axios.get(route('chat.appearance'));
        appearance.value = response.data;

        // Apply color theme if available
        if (appearance.value.color_theme) {
            const theme = appearance.value.color_theme;
            const shade = appearance.value.color_shade || 500;
            document.documentElement.style.setProperty('--primary', `var(--${theme}-${shade})`);
            document.documentElement.style.setProperty('--chat-bubble-color', `var(--${theme}-chat-bubble)`);
            document.documentElement.style.setProperty('--chat-text-color', `var(--${theme}-chat-text)`);
        }
    } catch (error) {
        console.error('Failed to fetch appearance:', error);
    } finally {
        isLoadingAppearance.value = false;
    }
};

onMounted(() => {
    // Fetch both starters and appearance
    Promise.all([
        axios.get(route('chat.starters')).then(response => {
            starters.value = response.data;
        }),
        fetchAppearance()
    ]);

})

function updateSelectedModel(newModel) {
  selectedModel.value = newModel;
  localStorage.setItem('selectedModel', newModel.id);
}

async function createNewConversation(message, model) {
  await axios.post(route('conversations.create'), { message: message, model: model.id }).then(response => {
    router.visit(route('chat.show', { conversationId: response.data.id, model: model.id }));
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
                  <!-- Loading skeleton for title -->
                  <div v-if="isLoadingAppearance" class="mb-8 text-center">
                      <div class="h-12 bg-muted rounded-lg w-96 mx-auto animate-pulse"></div>
                  </div>

                  <!-- Dynamic welcome message -->
                  <h1 v-else class="mb-8 text-center text-4xl font-medium text-gray-800 dark:text-white">
                      {{ appearance.welcome_message }}
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
                      <Composer
                          :text="selectedStarter"
                          @modelSelected="updateSelectedModel"
                          @messageAdded="createNewConversation"
                      />

                      <!-- Loading skeleton for footer -->
                      <div v-if="isLoadingAppearance" class="text-center mt-4">
                          <div class="h-4 bg-muted rounded w-80 mx-auto animate-pulse"></div>
                      </div>

                      <!-- Dynamic footer text -->
                      <div v-else-if="appearance.chat_footer_text" class="text-center mt-4">
                          <p class="text-xs text-muted-foreground">
                              {{ appearance.chat_footer_text }}
                          </p>
                      </div>
                  </div>
              </div>
          </div>
      </template>

  </AppLayout>
</template>
