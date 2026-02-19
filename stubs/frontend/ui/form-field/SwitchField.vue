<script setup lang="ts">
import { SwitchProps } from '@/components/ui/switch';
import Switch from '../switch/Switch.vue';
import FormField, { type Props as FormFieldProps } from './FormField.vue';

/**
 * Disable default attribute inheritance to allow us to
 * use v-bind="$attrs" on the input component.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps<SwitchProps & FormFieldProps>();

const modelValue = defineModel<boolean | null>();
</script>

<template>
    <FormField :id="props.id" :classNames="props.classNames" :optional="props.optional" :label="props.label" :error="props.error">
        <slot name="label" />
        <div class="flex flex-1 items-center">
            <Switch :id="props.id" v-model="modelValue" :aria-invalid="Boolean(props.error)" v-bind="$attrs" />
        </div>
    </FormField>
</template>
