<script setup lang="ts">
import { Textarea, TextareaProps } from '@/components/ui/textarea';
import FormField, { type Props as FormFieldProps } from './FormField.vue';

const props = defineProps<TextareaProps & FormFieldProps>();

const modelValue = defineModel<string>();
</script>

<template>
    <FormField :id="props.id" :classNames="props.classNames" :optional="props.optional" :label="props.label" :error="props.error">
        <Textarea :id="props.id" v-model="modelValue" :class="props.class" :aria-invalid="Boolean(props.error)" v-bind="$attrs" />
    </FormField>
</template>
