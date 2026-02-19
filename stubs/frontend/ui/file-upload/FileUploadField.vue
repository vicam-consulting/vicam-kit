/** * FileUploadField - A file upload component with drag-and-drop support. * * ## Overview * This component provides a styled file upload zone that
supports: * - Click to open file picker * - Drag and drop files directly onto the zone * - Single or multiple file selection * - File type filtering
via `accept` prop * - Preview of selected files with remove functionality * - Support for both new files (File objects) and existing files (URLs) * *
## Usage * * ### Single file upload: * ```vue *
<FileUploadField v-model="photo" label="Profile Photo" accept="image/*" />
* ``` * * ### Multiple file upload: * ```vue *
<FileUploadField v-model="documents" label="Documents" :multiple="true" accept=".pdf,.doc" />
* ``` * * ### With existing file (e.g., from server): * ```vue *
<FileUploadField v-model="existingFile" />
* // where existingFile = { id: 1, fileUrl: 'https://...' } * ``` * * ## Model Value Structure (FileValue) * The v-model accepts/emits objects with
this shape: * - `id?: number` - Database ID for existing files * - `key?: string` - Unique identifier (auto-generated for new files) * - `fileUrl?:
string` - URL for existing files (used for preview) * - `file?: File | null` - The actual File object for new uploads * * In single mode: `FileValue |
null` * In multiple mode: `FileValue[] | null` * * ## Key Behaviors * - New files get a unique key like `file-new-{timestamp}-{random}` * - In
multiple mode, new files are appended to existing selection * - In single mode, selecting a new file replaces the current one * - Drag-and-drop
filters files by the `accept` prop * - The drop zone shows visual feedback (primary color) when dragging */
<script setup lang="ts">
import { cn } from '@/lib/style.utils';
import { computed, ref, useId } from 'vue';

import Icon from '@/components/Icon.vue';
import InputError from '@/components/ui/input-error/InputError.vue';
import { Label } from '@/components/ui/label';

import FileUploadPreview from './FileUploadPreview.vue';

interface Props {
    /** CSS class overrides for component parts */
    classNames?: {
        root?: string;
        content?: string;
        label?: string;
    };
    /** Label text displayed above the upload zone */
    label?: string;
    /** Custom placeholder text when no files are selected */
    placeholder?: string;
    /** Shows "(optional)" indicator next to the label */
    optional?: boolean;
    /** Error message to display below the upload zone */
    error?: string;

    /**
     * Acceptable file types (HTML input accept attribute format).
     * Supports MIME types (e.g., 'image/*', 'application/pdf') and extensions (e.g., '.pdf').
     * @default 'image/*'
     */
    accept?: string;

    /**
     * Allow multiple file selection. When true, new files are appended to existing selection.
     * @default false
     */
    multiple?: boolean;
}

/**
 * Represents a file in the upload field.
 * Can be a new file (with `file` property) or an existing file (with `fileUrl` property).
 */
interface FileValue {
    /** Database ID for existing files */
    id?: number;
    /** Unique identifier - auto-generated for new files as `file-new-{timestamp}-{random}` */
    key?: string;
    /** URL for existing files, used to display preview */
    fileUrl?: string;
    /** The actual File object for new uploads */
    file?: File | null;
}

const id = useId();

const props = withDefaults(defineProps<Props>(), {
    accept: 'image/*',
    multiple: false,
});

const emit = defineEmits<{
    (event: 'removeExistingFile', key: string): void;
}>();

const modelValue = defineModel<FileValue | FileValue[] | null>();

const fileInputRef = ref<HTMLInputElement | null>(null);
/** Tracks whether files are being dragged over the drop zone (for visual feedback) */
const isDragging = ref(false);

/**
 * Normalizes the model value to always be an array for easier iteration in the template.
 * Handles both single and multiple modes transparently.
 */
const modelItems = computed((): FileValue[] => {
    if (props.multiple) {
        if (Array.isArray(modelValue.value)) {
            return modelValue.value;
        }

        return modelValue.value ? [modelValue.value] : [];
    }

    // Single file mode - modelValue should be FileValue | null
    const singleValue = modelValue.value as FileValue | null;
    return singleValue ? [singleValue] : [];
});

/** Programmatically opens the native file picker dialog */
function handleFileClick(): void {
    fileInputRef.value?.click();
}

/**
 * Handles file selection from the native file input.
 * - In multiple mode: appends new files to existing selection
 * - In single mode: replaces the current file
 * - Resets the input value after processing to allow re-selecting the same file
 */
function handleFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const selectedFiles = target.files ? Array.from(target.files) : [];

    if (props.multiple) {
        // Handle multiple files - append new files to existing ones
        const currentItems = Array.isArray(modelValue.value) ? modelValue.value : [];

        // Create new file items for selected files
        const newItems: FileValue[] = selectedFiles.map((file) => {
            return {
                key: `file-new-${Date.now()}-${Math.random()}`,
                file,
            };
        });

        const allItems = [...currentItems, ...newItems];
        modelValue.value = allItems.length > 0 ? allItems : null;
    } else {
        const file = selectedFiles[0] ?? null;
        if (file) {
            modelValue.value = {
                key: `file-new-${Date.now()}-${Math.random()}`,
                file,
            };
        } else {
            modelValue.value = null;
        }
    }

    // Reset the input so the same files can be selected again if needed
    if (fileInputRef.value) fileInputRef.value.value = '';
}

/**
 * Removes a file from the selection.
 * - In multiple mode: filters out the specific item from the array
 * - In single mode: sets the model value to null
 */
function handleRemoveFile(item: FileValue): void {
    if (props.multiple) {
        const currentItems = Array.isArray(modelValue.value) ? modelValue.value : [];
        const filteredItems = currentItems.filter((modelItem) => modelItem !== item);
        modelValue.value = filteredItems.length > 0 ? filteredItems : null;
    } else {
        modelValue.value = null;
    }
}

/**
 * Processes an array of File objects (from drag-and-drop).
 * Filters files by the `accept` prop before adding them to the model.
 *
 * Accept filtering supports:
 * - Wildcard MIME types: 'image/*' matches any image type
 * - Exact MIME types: 'application/pdf'
 * - File extensions: '.pdf', '.doc' (case-insensitive)
 */
function processFiles(files: File[]): void {
    // Filter files by accept type if specified
    const filteredFiles = files.filter((file) => {
        if (!props.accept) return true;

        const acceptTypes = props.accept.split(',').map((type) => type.trim());
        return acceptTypes.some((type) => {
            if (type.endsWith('/*')) {
                const baseType = type.slice(0, -2);
                return file.type.startsWith(baseType);
            }
            return file.type === type || file.name.toLowerCase().endsWith(type.toLowerCase());
        });
    });

    if (filteredFiles.length === 0) return;

    if (props.multiple) {
        const currentItems = Array.isArray(modelValue.value) ? modelValue.value : [];
        const newItems: FileValue[] = filteredFiles.map((file) => ({
            key: `file-new-${Date.now()}-${Math.random()}`,
            file,
        }));
        const allItems = [...currentItems, ...newItems];
        modelValue.value = allItems.length > 0 ? allItems : null;
    } else {
        const file = filteredFiles[0];
        modelValue.value = {
            key: `file-new-${Date.now()}-${Math.random()}`,
            file,
        };
    }
}

// ============================================================================
// Drag and Drop Event Handlers
// ============================================================================

/** Activates the visual drag feedback when files enter the drop zone */
function handleDragEnter(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = true;
}

/**
 * Deactivates the visual drag feedback when files leave the drop zone.
 * Uses relatedTarget check to avoid flickering when dragging over child elements.
 */
function handleDragLeave(event: DragEvent): void {
    event.preventDefault();
    // Only set to false if we're leaving the drop zone entirely
    const relatedTarget = event.relatedTarget as Node | null;
    const currentTarget = event.currentTarget as Node;
    if (!relatedTarget || !currentTarget.contains(relatedTarget)) {
        isDragging.value = false;
    }
}

/** Required to allow dropping - must prevent default to enable the drop event */
function handleDragOver(event: DragEvent): void {
    event.preventDefault();
}

/** Processes dropped files through the same pipeline as selected files */
function handleDrop(event: DragEvent): void {
    event.preventDefault();
    isDragging.value = false;

    const droppedFiles = event.dataTransfer?.files;
    if (droppedFiles && droppedFiles.length > 0) {
        processFiles(Array.from(droppedFiles));
    }
}
</script>

<template>
    <div :class="cn('flex flex-col gap-1', classNames?.root)">
        <Label v-if="label" :for="id" :class="cn('text-sm font-medium', classNames?.label)">
            {{ label }}
            <span v-if="optional" class="text-xs font-normal text-neutral-500">(optional)</span>
        </Label>

        <div
            :class="
                cn(
                    'relative flex min-h-[88px] w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-4 transition-colors hover:border-neutral-400 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900/30 dark:hover:border-neutral-600 dark:hover:bg-neutral-900/50',
                    isDragging && 'border-primary-500 bg-primary-50 dark:border-primary-400 dark:bg-primary-900/20',
                    classNames?.content,
                )
            "
            @click="handleFileClick"
            @dragenter="handleDragEnter"
            @dragleave="handleDragLeave"
            @dragover="handleDragOver"
            @drop="handleDrop"
        >
            <input :id="id" ref="fileInputRef" type="file" :accept="accept" :multiple="multiple" class="hidden" @change="handleFileChange" />

            <slot>
                <template v-if="modelItems.length > 0">
                    <!-- Unified file preview view -->
                    <div class="flex w-full flex-col gap-3">
                        <div :class="props.multiple ? 'grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4' : 'flex justify-center'">
                            <FileUploadPreview
                                v-for="(item, index) in modelItems"
                                :key="item.key ?? item.id ?? `file-${index}`"
                                :file="item.file ?? null"
                                :file-url="item.fileUrl ?? null"
                                :allow-remove="true"
                                @remove="handleRemoveFile(item)"
                            />
                        </div>

                        <p v-if="props.multiple" class="text-center text-xs text-neutral-400 dark:text-neutral-500">
                            {{ modelItems.length }} file{{ modelItems.length !== 1 ? 's' : '' }} selected
                        </p>

                        <p class="text-center text-xs text-neutral-400 dark:text-neutral-500">
                            {{ props.multiple ? 'Click to add more files' : 'Click to replace' }}
                        </p>
                    </div>
                </template>
            </slot>

            <template v-if="!modelItems.length">
                <Icon name="upload" />
                <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">
                    {{ placeholder ?? (multiple ? 'Drag and drop files here or click to upload' : 'Drag and drop a file here or click to upload') }}
                </p>
            </template>
        </div>

        <InputError v-if="error" :message="error" />
    </div>
</template>
