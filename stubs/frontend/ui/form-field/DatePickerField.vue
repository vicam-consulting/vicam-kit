<script setup lang="ts">
import { DatePicker, DatePickerProps } from '@/components/ui/date-picker';

import FormField, { type Props as FormFieldProps } from './FormField.vue';

const props = defineProps<DatePickerProps & FormFieldProps>();

const modelValue = defineModel<string>();
</script>

<template>
    <FormField :id="props.id" :classNames="props.classNames" :optional="props.optional" :label="props.label" :error="props.error">
        <DatePicker :id="props.id" :type="props.type" v-model="modelValue" :class="props.class" :aria-invalid="Boolean(props.error)" />
    </FormField>
</template>
