<script setup lang="ts">
import { computed, useSlots } from 'vue';

interface Props {
    title: string;
    detail?: string | number | null;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    detail: undefined,
    class: '',
});

const slots = useSlots();
const hasSlotContent = computed(() => !!slots.default);
const shouldRender = computed(() => {
    if (hasSlotContent.value) {
        return true;
    }
    return !!props.detail;
});
</script>

<template>
    <div v-if="shouldRender" :class="['flex flex-col space-y-1 overflow-hidden whitespace-nowrap', props.class]">
        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ title }}</p>
        <slot v-if="hasSlotContent" />
        <p v-else class="text-sm text-neutral-900 dark:text-neutral-100">
            {{ detail }}
        </p>
    </div>
</template>
