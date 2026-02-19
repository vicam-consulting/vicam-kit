<script setup lang="ts">
import { cn } from '@/lib/style.utils';
import { reactiveOmit } from '@vueuse/core';
import type { ComboboxContentEmits, ComboboxContentProps } from 'reka-ui';
import { ComboboxContent, ComboboxPortal, ComboboxViewport, useForwardPropsEmits } from 'reka-ui';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<
        ComboboxContentProps & {
            class?: HTMLAttributes['class'];
            viewportClass?: HTMLAttributes['class'];
        }
    >(),
    {
        position: 'popper',
    },
);
const emits = defineEmits<ComboboxContentEmits>();

const delegated = reactiveOmit(props, 'class', 'viewportClass');
const forwarded = useForwardPropsEmits(delegated, emits);
</script>

<template>
    <ComboboxPortal>
        <ComboboxContent
            data-slot="combobox-list"
            v-bind="{ ...forwarded, ...$attrs }"
            :class="
                cn(
                    'relative z-50 max-h-(--reka-combobox-content-available-height) min-w-[8rem] overflow-x-hidden overflow-y-auto rounded-md border bg-popover text-popover-foreground shadow-md data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    props.position === 'popper' &&
                        'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
                    props.class,
                )
            "
        >
            <ComboboxViewport
                :class="
                    cn(
                        'p-1',
                        props.position === 'popper' &&
                            'h-[var(--reka-combobox-trigger-height)] w-full min-w-[var(--reka-combobox-trigger-width)] scroll-my-1',
                        props.viewportClass,
                    )
                "
            >
                <slot />
            </ComboboxViewport>
        </ComboboxContent>
    </ComboboxPortal>
</template>
