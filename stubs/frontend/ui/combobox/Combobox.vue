<script setup lang="ts">
import { reactiveOmit } from '@vueuse/core';
import type { ComboboxRootEmits, ComboboxRootProps } from 'reka-ui';
import { ComboboxRoot, useForwardPropsEmits } from 'reka-ui';
import type { HTMLAttributes } from 'vue';

const props = defineProps<
    ComboboxRootProps & {
        class?: HTMLAttributes['class'];
    }
>();
const emits = defineEmits<ComboboxRootEmits>();

const delegated = reactiveOmit(props, 'class');
const forwarded = useForwardPropsEmits(delegated, emits);
</script>

<template>
    <ComboboxRoot data-slot="combobox-root" v-bind="forwarded" :class="props.class">
        <slot />
    </ComboboxRoot>
</template>
