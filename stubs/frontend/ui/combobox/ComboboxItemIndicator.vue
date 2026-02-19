<script setup lang="ts">
import { reactiveOmit } from '@vueuse/core';
import type { ComboboxItemIndicatorProps } from 'reka-ui';
import { ComboboxItemIndicator, useForwardProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';

const props = defineProps<
    ComboboxItemIndicatorProps & {
        class?: HTMLAttributes['class'];
    }
>();

const delegated = reactiveOmit(props, 'class');
const forwarded = useForwardProps(delegated);
</script>

<template>
    <ComboboxItemIndicator v-bind="forwarded" :class="props.class">
        <slot />
    </ComboboxItemIndicator>
</template>
