<script setup lang="ts">
import { reactiveOmit } from '@vueuse/core';
import type { ComboboxAnchorProps } from 'reka-ui';
import { ComboboxAnchor, useForwardProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';

const props = defineProps<
    ComboboxAnchorProps & {
        class?: HTMLAttributes['class'];
    }
>();

const delegated = reactiveOmit(props, 'class');
const forwarded = useForwardProps(delegated);
</script>

<template>
    <ComboboxAnchor data-slot="combobox-anchor" v-bind="forwarded" :class="props.class">
        <slot />
    </ComboboxAnchor>
</template>
