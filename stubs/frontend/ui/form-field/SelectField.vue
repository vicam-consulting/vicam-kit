<script setup lang="ts">
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/style.utils';
import type { SelectRootEmits, SelectRootProps } from 'reka-ui';
import { useForwardPropsEmits } from 'reka-ui';
import FormField, { type Props as FormFieldProps } from './FormField.vue';

const props = defineProps<
    SelectRootProps &
        FormFieldProps & {
            classNames?: FormFieldProps['classNames'] & {
                selectRoot?: string;
                selectTrigger?: string;
                selectValue?: string;
            };
            placeholder?: string;
        }
>();
const emits = defineEmits<SelectRootEmits>();

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
    <FormField :id="props.id" :classNames="props.classNames" :optional="props.optional" :label="props.label" :error="props.error">
        <Select data-slot="select" v-bind="forwarded" :class="props.classNames?.selectRoot">
            <SelectTrigger
                class="w-full"
                :aria-invalid="Boolean(props.error)"
                :id
                :class="
                    cn(props.classNames?.selectTrigger, {
                        'border-destructive ring-destructive/20 dark:ring-destructive/40': props.error,
                    })
                "
            >
                <SelectValue :placeholder="placeholder" :class="props.classNames?.selectValue" />
            </SelectTrigger>

            <slot />
        </Select>
    </FormField>
</template>
