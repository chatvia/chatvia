<script setup lang="ts">
import { ref, watch } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

interface Props {
  title?: string;
  description?: string;
  confirmText?: string;
  cancelText?: string;
  open?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Delete confirmation',
  description: 'This action cannot be undone.',
  confirmText: 'Yes, delete',
  cancelText: 'No, keep it',
  open: false
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'confirm'): void;
  (e: 'cancel'): void;
}>();

const isOpen = ref(props.open);

const confirm = () => {
  emit('confirm');
  isOpen.value = false;
  emit('update:open', false);
};

const cancel = () => {
  emit('cancel');
  isOpen.value = false;
  emit('update:open', false);
};

// Watch for changes in the open prop
watch(() => props.open, (newValue) => {
  isOpen.value = newValue;
});

// Emit changes in the isOpen ref
watch(isOpen, (newValue) => {
  emit('update:open', newValue);
});
</script>

<template>
  <Dialog v-model:open="isOpen">
    <DialogTrigger as-child>
      <slot name="trigger"></slot>
    </DialogTrigger>
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription>
          {{ description }}
        </DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" @click="cancel">
          {{ cancelText }}
        </Button>
        <Button variant="destructive" @click="confirm">
          {{ confirmText }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
