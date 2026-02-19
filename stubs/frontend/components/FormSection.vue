<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    description: '',
    class: '',
});
</script>

<template>
    <div :class="`flex w-full flex-col items-start justify-start gap-4 ${props.class}`">
        <div class="flex w-full items-start justify-between">
            <div class="flex w-full flex-col items-start justify-start gap-1">
                <h2 class="text-lg font-semibold tracking-tight text-foreground md:text-xl">{{ props.title }}</h2>
                <p v-if="props.description" class="text-sm text-muted-foreground">{{ props.description }}</p>
            </div>
            <slot name="action" />
        </div>
        <div class="w-full">
            <slot />
        </div>
    </div>
</template>
