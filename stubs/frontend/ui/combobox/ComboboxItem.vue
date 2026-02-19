<script setup lang="ts">
import { cn } from '@/lib/style.utils';
import { reactiveOmit } from '@vueuse/core';
import { Check } from 'lucide-vue-next';
import type { ComboboxItemEmits, ComboboxItemProps } from 'reka-ui';
import { ComboboxItem, ComboboxItemIndicator, useForwardPropsEmits } from 'reka-ui';
import type { HTMLAttributes } from 'vue';

const props = defineProps<
    ComboboxItemProps & {
        class?: HTMLAttributes['class'];
    }
>();
const emits = defineEmits<ComboboxItemEmits>();

const delegated = reactiveOmit(props, 'class');
const forwarded = useForwardPropsEmits(delegated, emits);
</script>

<template>
    <ComboboxItem
        data-slot="combobox-item"
        v-bind="forwarded"
        :class="
            cn(
                `relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-sm outline-hidden transition-colors select-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50`,
                `[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 [&_svg:not([class*='text-'])]:text-muted-foreground *:[span]:last:flex *:[span]:last:items-center *:[span]:last:gap-2`,
                props.class,
            )
        "
    >
        <span class="absolute right-2 flex size-3.5 items-center justify-center">
            <ComboboxItemIndicator>
                <Check class="size-4" />
            </ComboboxItemIndicator>
        </span>
        <slot />
    </ComboboxItem>
</template>
