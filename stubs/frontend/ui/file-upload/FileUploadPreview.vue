<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { getDocumentType } from '@/lib/file.utils';
import { computed, onUnmounted } from 'vue';

type Props = {
    allowRemove: boolean;
    file: File | null;
    fileUrl: string | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (event: 'remove'): void;
}>();

const fileUrl = computed(() => {
    if (props.fileUrl) return props.fileUrl;
    return props.file ? URL.createObjectURL(props.file) : null;
});

const documentType = computed(() => getDocumentType(props.file, fileUrl.value));

function handleDownload(event: MouseEvent): void {
    event.stopPropagation();
    if (fileUrl.value) window.open(fileUrl.value, '_blank');
}

function handleRemove(event: MouseEvent): void {
    event.stopPropagation();
    emit('remove');
}

onUnmounted((): void => {
    if (fileUrl.value) URL.revokeObjectURL(fileUrl.value);
});
</script>

<template>
    <div
        class="group relative flex max-w-48 flex-col items-center gap-1 rounded-md border border-neutral-200 bg-neutral-50 p-2 dark:border-neutral-700 dark:bg-neutral-800"
    >
        <!-- Remove button for new files or existing files when parent listens -->
        <button
            v-if="allowRemove"
            type="button"
            class="absolute -top-1 -right-1 z-10 flex h-5 w-5 cursor-pointer items-center justify-center rounded-full bg-destructive text-white opacity-0 transition-opacity group-hover:opacity-100 hover:bg-destructive/90"
            @click="handleRemove($event)"
        >
            <Icon name="x" size="12" />
            <span class="sr-only">Remove file</span>
        </button>

        <!-- Image preview -->
        <template v-if="documentType === 'image' && fileUrl">
            <img :src="fileUrl" alt="Uploaded file" class="h-16 w-16 rounded-md object-contain shadow-sm" />
        </template>

        <!-- Document preview (non-image) -->
        <template v-else>
            <div class="flex h-16 w-16 items-center justify-center rounded-md border bg-neutral-100 dark:bg-neutral-700">
                <Icon name="fileText" size="48" class="text-neutral-400 dark:text-neutral-500" />
            </div>
        </template>

        <!-- Download button for existing files -->
        <button
            v-if="fileUrl"
            type="button"
            class="flex items-center gap-1 rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary transition-colors hover:bg-primary/20"
            @click="handleDownload($event)"
        >
            <Icon name="download" />
            View
        </button>
    </div>
</template>
