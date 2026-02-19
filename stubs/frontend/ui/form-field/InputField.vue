<script setup lang="ts">
import { Input, InputProps } from '@/components/ui/input';
import FormField, { type Props as FormFieldProps } from './FormField.vue';

/**
 * Disable default attribute inheritance to allow us to
 * use v-bind="$attrs" on the input component.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps<InputProps & FormFieldProps>();

const modelValue = defineModel<string | number>();
</script>

<template>
    <FormField :id="props.id" :classNames="props.classNames" :optional="props.optional" :label="props.label" :error="props.error">
        <slot name="label" />
        <Input :id="props.id" v-model="modelValue" :class="props.class" :aria-invalid="Boolean(props.error)" v-bind="$attrs" />
    </FormField>
</template>
