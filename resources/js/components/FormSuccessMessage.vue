<script setup lang="ts">
import { CheckCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
  show: boolean;
  message?: string;
  icon?: boolean;
  class?: string;
}

const props = withDefaults(defineProps<Props>(), {
  message: 'Saved successfully',
  icon: true,
  class: ''
});

const classes = computed(() => `flex items-center text-sm text-green-600 ${props.class}`);
</script>

<template>
  <Transition
    enter-active-class="transition ease-in-out duration-300"
    enter-from-class="opacity-0 transform translate-x-4"
    enter-to-class="opacity-100 transform translate-x-0"
    leave-active-class="transition ease-in-out duration-300"
    leave-from-class="opacity-100 transform translate-x-0"
    leave-to-class="opacity-0 transform translate-x-4"
  >
    <div v-show="show" :class="classes" class="mr-2 ml-2">
      <CheckCircle v-if="icon" class="mr-1 size-4 ml-1" />
      <span>{{ message }}</span>
    </div>
  </Transition>
</template>
