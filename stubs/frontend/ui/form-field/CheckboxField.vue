<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { Checkbox, CheckboxProps } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { type Props as FormFieldProps } from './FormField.vue';

/**
 * Disable default attribute inheritance to allow us to
 * use v-bind="$attrs" on the input component.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps<
    CheckboxProps & FormFieldProps & { class?: HTMLAttributes['class'] }
>();

const modelValue = defineModel<boolean | 'indeterminate' | null>();
</script>

<template>
    <div class="flex items-center gap-2">
        <Checkbox :id="props.id" v-model="modelValue" :class="props.class" :aria-invalid="Boolean(props.error)" v-bind="$attrs" />
        <Label
            v-if="props.label"
            :for="props.id"
            class="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
        >
            {{ props.label }}
        </Label>
    </div>
</template>
