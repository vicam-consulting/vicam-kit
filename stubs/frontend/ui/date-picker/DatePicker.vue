<script setup lang="ts">
import { cn } from '@/lib/style.utils';
import { useVModel } from '@vueuse/core';
import { Calendar, Clock } from 'lucide-vue-next';
import { computed, HTMLAttributes } from 'vue';

export interface Props {
    id: string;
    type: 'date' | 'datetime-local';
    defaultValue?: string;
    modelValue?: string;
    class?: HTMLAttributes['class'];
}

const props = defineProps<Props>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});

const iconComponent = computed(() => {
    return props.type === 'date' ? Calendar : Clock;
});

const openDatePicker = (event: Event) => {
    const target = event.currentTarget as HTMLElement;
    const input = target?.parentElement?.querySelector('input') as HTMLInputElement;
    if (input) {
        input.focus();
        input.showPicker?.();
    }
};
</script>

<template>
    <div class="relative">
        <input
            :id="props.id"
            v-model="modelValue"
            :type="props.type"
            data-slot="input"
            :class="
                cn(
                    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 pr-9 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30',
                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                    '[&::-webkit-calendar-picker-indicator]:hidden [&::-webkit-datetime-edit]:pr-0',
                    props.class,
                )
            "
            v-bind="$attrs"
        />
        <component
            :is="iconComponent"
            class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 cursor-pointer text-neutral-500 transition-colors hover:text-neutral-700 dark:hover:text-neutral-300"
            @click="openDatePicker"
            role="button"
            tabindex="0"
            :aria-label="`Open ${props.type} picker`"
        />
    </div>
</template>
