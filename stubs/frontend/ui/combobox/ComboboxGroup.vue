<script setup lang="ts">
import { cn } from '@/lib/style.utils';
import { reactiveOmit } from '@vueuse/core';
import type { ComboboxGroupProps } from 'reka-ui';
import { ComboboxGroup, useForwardProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';

const props = defineProps<
    ComboboxGroupProps & {
        class?: HTMLAttributes['class'];
    }
>();

const delegated = reactiveOmit(props, 'class');
const forwarded = useForwardProps(delegated);
</script>

<template>
    <ComboboxGroup data-slot="combobox-group" v-bind="forwarded" :class="cn('overflow-hidden rounded-md p-1 text-foreground', props.class)">
        <slot />
    </ComboboxGroup>
</template>
