<script setup lang="ts">
import { cn } from '@/lib/style.utils';
import { reactiveOmit } from '@vueuse/core';
import type { ComboboxEmptyProps } from 'reka-ui';
import { ComboboxEmpty, useForwardProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';

const props = defineProps<
    ComboboxEmptyProps & {
        class?: HTMLAttributes['class'];
    }
>();

const delegated = reactiveOmit(props, 'class');
const forwarded = useForwardProps(delegated);
</script>

<template>
    <ComboboxEmpty data-slot="combobox-empty" v-bind="forwarded" :class="cn('py-6 text-center text-sm text-muted-foreground', props.class)">
        <slot />
    </ComboboxEmpty>
</template>
