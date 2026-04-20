<script setup lang="ts">
import * as icons from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    name: string;
    class?: string;
    size?: number | string;
    color?: string;
    strokeWidth?: number | string;
}

const props = withDefaults(defineProps<Props>(), {
    class: '',
    size: 16,
    strokeWidth: 2,
});

const icon = computed(() => {
    const iconName = props.name.charAt(0).toUpperCase() + props.name.slice(1);

    return (icons as Record<string, any>)[iconName];
});
</script>

<template>
    <component
        :is="icon"
        :class="props.class"
        :size="size"
        :stroke-width="strokeWidth"
        :color="color"
    />
</template>
