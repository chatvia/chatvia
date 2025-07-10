<template>
  <div class="loading-overlay" v-if="overlay">
    <div class="loading-backdrop"></div>
    <div class="loading-spinner-container" :class="{ 'inline': inline }">
      <div class="loading-spinner" :class="sizeClass">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
      </div>
      <span v-if="text" class="loading-text">{{ text }}</span>
    </div>
  </div>
  <div v-else class="loading-spinner-container" :class="{ 'inline': inline }">
    <div class="loading-spinner" :class="sizeClass">
      <div></div>
      <div></div>
      <div></div>
      <div></div>
    </div>
    <span v-if="text" class="loading-text">{{ text }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value)
  },
  text: {
    type: String,
    default: ''
  },
  inline: {
    type: Boolean,
    default: false
  },
  overlay: {
    type: Boolean,
    default: false
  }
});

const sizeClass = computed(() => {
  return {
    'spinner-sm': props.size === 'sm',
    'spinner-md': props.size === 'md',
    'spinner-lg': props.size === 'lg'
  };
});
</script>

<style scoped>
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.loading-backdrop {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(4px);
  z-index: -1;
}

.dark .loading-backdrop {
  background-color: rgba(17, 24, 39, 0.8);
}

.loading-spinner-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  border-radius: 0.75rem;
}

.loading-overlay .loading-spinner-container {
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.dark .loading-overlay .loading-spinner-container {
  background-color: #1f2937;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
}

.loading-spinner-container.inline {
  display: inline-flex;
  flex-direction: row;
  gap: 0.75rem;
}

.loading-text {
  margin-top: 1rem;
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 500;
}

.dark .loading-text {
  color: #9ca3af;
}

.inline .loading-text {
  margin-top: 0;
}

.loading-spinner {
  display: inline-block;
  position: relative;
  width: 40px;
  height: 40px;
}

.spinner-sm {
  width: 24px;
  height: 24px;
}

.spinner-lg {
  width: 56px;
  height: 56px;
}

.loading-spinner div {
  box-sizing: border-box;
  display: block;
  position: absolute;
  width: 80%;
  height: 80%;
  margin: 10%;
  border: 3px solid transparent;
  border-radius: 50%;
  animation: loading-spinner 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
  border-color: #3b82f6 transparent transparent transparent;
}

.dark .loading-spinner div {
  border-color: #60a5fa transparent transparent transparent;
}

.spinner-sm div {
  border-width: 2px;
}

.spinner-lg div {
  border-width: 4px;
}

.loading-spinner div:nth-child(1) {
  animation-delay: -0.45s;
}

.loading-spinner div:nth-child(2) {
  animation-delay: -0.3s;
}

.loading-spinner div:nth-child(3) {
  animation-delay: -0.15s;
}

@keyframes loading-spinner {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
</style>
