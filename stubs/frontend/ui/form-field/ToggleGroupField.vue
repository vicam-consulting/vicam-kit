<script setup lang="ts">
import { ToggleGroup, ToggleGroupProps } from '@/components/ui/toggle-group';
import { cn } from '@/lib/style.utils';
import { reactiveOmit } from '@vueuse/core';
import type { ToggleGroupRootEmits } from 'reka-ui';
import { useForwardPropsEmits } from 'reka-ui';

import FormField, { type Props as FormFieldProps } from './FormField.vue';

const props = defineProps<ToggleGroupProps & FormFieldProps>();
const emits = defineEmits<ToggleGroupRootEmits>();

const delegatedProps = reactiveOmit(props, 'classNames', 'size', 'variant');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <FormField :id="props.id" :classNames="props.classNames" :optional="props.optional" :label="props.label" :error="props.error">
        <ToggleGroup
            v-slot="slotProps"
            data-slot="toggle-group"
            :data-size="size"
            :data-variant="variant"
            :aria-invalid="Boolean(props.error)"
            v-bind="forwarded"
            :class="
                cn(
                    'group/toggle-group flex items-center rounded-md border border-transparent transition-colors data-[variant=outline]:shadow-xs',
                    'aria-invalid:border-destructive',
                    props.class,
                )
            "
        >
            <slot v-bind="slotProps" />
        </ToggleGroup>
    </FormField>
</template>
