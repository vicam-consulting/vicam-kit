<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/style.utils';
import InputError from '../input-error/InputError.vue';

export interface Props {
    id: string;
    classNames?: {
        root?: string;
        label?: string;
        error?: string;
    };
    error?: string;
    optional?: boolean;
    label?: string;
}

const props = defineProps<Props>();
</script>

<template>
    <div :class="cn('flex flex-col gap-1', props.error && 'error', props.classNames?.root)" :data-error="props.error ? 'true' : undefined">
        <Label v-if="label" :for="id" :class="props.classNames?.label">
            {{ label }}
            <span v-if="optional" class="text-xs text-neutral-500">(optional)</span>
        </Label>
        <slot />
        <InputError v-if="error" :message="error" :class="props.classNames?.error" />
    </div>
</template>
