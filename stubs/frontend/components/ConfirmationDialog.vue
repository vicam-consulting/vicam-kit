<script setup lang="ts" generic="T">
import type { VariantProps } from 'class-variance-authority';
import { cva } from 'class-variance-authority';
import { ref } from 'vue';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

const variants = cva('', {
    variants: {
        variant: {
            default: '',
            destructive:
                'bg-destructive text-destructive-foreground hover:bg-destructive/90',
        },
    },
});
type Variants = VariantProps<typeof variants>;

defineProps<{
    title: string;
    confirmText?: string;
    cancelText?: string;
    variant?: Variants['variant'];
}>();

const emit = defineEmits<{
    (e: 'confirm', data: T | null): void;
    (e: 'cancel'): void;
}>();

const isOpen = ref(false);
const data = ref<T | null>(null);

defineExpose({ openDialog });

function openDialog(value: T) {
    data.value = value;
    isOpen.value = true;
}

function closeDialog() {
    isOpen.value = false;
    setTimeout(() => {
        data.value = null;
    }, 300);
}

const handleConfirm = () => {
    emit('confirm', data.value);
};

function handleCancel() {
    emit('cancel');
}

const handleUpdateOpen = (open: boolean) => {
    if (!open) {
        closeDialog();
    }
};
</script>

<template>
    <AlertDialog :open="isOpen" @update:open="handleUpdateOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{ title }}
                </AlertDialogTitle>
            </AlertDialogHeader>

            <AlertDialogDescription>
                <slot :data="data" />
            </AlertDialogDescription>

            <AlertDialogFooter>
                <AlertDialogCancel @click="handleCancel">
                    {{ cancelText ?? 'Cancel' }}
                </AlertDialogCancel>
                <AlertDialogAction
                    :class="variants({ variant })"
                    @click="handleConfirm"
                >
                    {{ confirmText ?? 'Confirm' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
