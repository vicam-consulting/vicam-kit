<script setup lang="ts">
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList } from '@/components/ui/combobox';
import { cn } from '@/lib/style.utils';
import { useDebounceFn } from '@vueuse/core';
import { LoaderCircle, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import FormField, { type Props as FormFieldProps } from './FormField.vue';

export interface ComboboxOption<T = unknown> {
    value: string | number;
    label: string;
    description?: string | null;
    meta?: T;
}

interface Props extends FormFieldProps {
    fetchOptions: (query: string, signal?: AbortSignal) => Promise<Array<ComboboxOption>>;
    /**
     * The autocomplete attribute for the input field.
     * @default 'off'
     */
    autocomplete?: string;
    placeholder?: string;
    emptyMessage?: string;
    loadingMessage?: string;
    noQueryMessage?: string;
    minQueryLength?: number;
    debounce?: number;
    initialOptions?: Array<ComboboxOption>;
    displayValue?: (option: ComboboxOption | null) => string;
    disabled?: boolean;
    openOnFocus?: boolean;
    openOnClick?: boolean;
    preserveSelected?: boolean;
    allowClear?: boolean;
    classNames?: FormFieldProps['classNames'] & {
        anchor?: string;
        input?: string;
        list?: string;
        item?: string;
    };
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Search...',
    emptyMessage: 'No results found',
    loadingMessage: 'Searching...',
    noQueryMessage: 'Type to search',
    minQueryLength: 1,
    debounce: 250,
    initialOptions: () => [],
    disabled: false,
    openOnFocus: false,
    openOnClick: true,
    preserveSelected: true,
    allowClear: false,
    autocomplete: 'off',
});

const emit = defineEmits<{
    (e: 'cleared'): void;
}>();

const modelValue = defineModel<ComboboxOption | null>({ default: null });

const searchTerm = ref('');
const isOpen = ref(false);
const isLoading = ref(false);
const options = ref<Array<ComboboxOption>>([...props.initialOptions]);
const fetchError = ref<string | null>(null);

const minCharacters = computed(() => Math.max(props.minQueryLength, 0));
const trimmedSearch = computed(() => searchTerm.value.trim());
const hasSufficientQuery = computed(() => trimmedSearch.value.length >= minCharacters.value);
const displayValue = computed(() => props.displayValue ?? ((option: ComboboxOption | null) => option?.label ?? ''));
const selectedValueKey = computed(() => (modelValue.value ? getOptionKey(modelValue.value) : null));
const showClearButton = computed(() => props.allowClear && !isLoading.value && Boolean(modelValue.value !== null || searchTerm.value));

let abortController: AbortController | null = null;

const debouncedSearch = useDebounceFn((term: string) => {
    void performSearch(term);
}, props.debounce);

function cancelDebouncedSearch(): void {
    const handler = debouncedSearch as typeof debouncedSearch & { cancel?: () => void };
    handler.cancel?.();
}

watch(
    () => props.initialOptions,
    (next) => {
        options.value = [...next];
    },
    { deep: true },
);

watch(
    modelValue,
    (selected) => {
        if (!props.preserveSelected || !selected) {
            return;
        }

        const key = getOptionKey(selected);
        if (!options.value.some((option) => getOptionKey(option) === key)) {
            options.value = [selected, ...options.value];
        }
    },
    { immediate: true },
);

watch(
    () => trimmedSearch.value,
    (term, previous) => {
        if (!isOpen.value) {
            return;
        }

        if (!term && !previous) {
            return;
        }

        if (term.length < minCharacters.value) {
            clearPendingSearch();
            isLoading.value = false;
            fetchError.value = null;
            options.value = props.initialOptions.length
                ? [...props.initialOptions]
                : props.preserveSelected && modelValue.value
                  ? [modelValue.value]
                  : [];
            return;
        }

        debouncedSearch(term);
    },
);

watch(isOpen, (open) => {
    if (open) {
        if (trimmedSearch.value.length >= minCharacters.value) {
            cancelDebouncedSearch();
            void performSearch(trimmedSearch.value);
        }
    } else {
        clearPendingSearch();
    }
});

onBeforeUnmount(() => {
    clearPendingSearch();
});

function getOptionKey(option: ComboboxOption): string {
    return `${option.value}`;
}

async function performSearch(term: string): Promise<void> {
    const query = term.trim();

    if (!query || query.length < minCharacters.value) {
        return;
    }

    clearPendingSearch();

    const controller = new AbortController();
    abortController = controller;
    isLoading.value = true;
    fetchError.value = null;

    try {
        const result = await props.fetchOptions(query, controller.signal);

        if (controller.signal.aborted) {
            return;
        }

        options.value = normalizeOptions(result);

        if (props.preserveSelected && modelValue.value) {
            const selectedKey = selectedValueKey.value;
            if (selectedKey && !options.value.some((option) => getOptionKey(option) === selectedKey)) {
                options.value = [modelValue.value, ...options.value];
            }
        }
    } catch (error) {
        if (controller.signal.aborted) {
            return;
        }

        fetchError.value = error instanceof Error ? error.message : 'Unable to fetch results';
        options.value = [];
    } finally {
        if (!controller.signal.aborted) {
            isLoading.value = false;
        }
    }
}

function normalizeOptions(result: Array<ComboboxOption>): Array<ComboboxOption> {
    const seen = new Set<string>();

    return result.filter((option) => {
        const key = getOptionKey(option);
        if (!key) {
            return false;
        }

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);
        return true;
    });
}

function clearPendingSearch(): void {
    cancelDebouncedSearch();
    if (abortController) {
        abortController.abort();
        abortController = null;
    }
}

function isSelected(option: ComboboxOption): boolean {
    return getOptionKey(option) === selectedValueKey.value;
}

function clearSelection(): void {
    const hadValue = modelValue.value !== null || searchTerm.value.length > 0;
    modelValue.value = null;
    searchTerm.value = '';
    if (hadValue) {
        emit('cleared');
    }
}
</script>

<template>
    <FormField :id="props.id" :label="props.label" :optional="props.optional" :error="props.error" :class-names="props.classNames">
        <Combobox
            v-model="modelValue"
            v-model:open="isOpen"
            by="value"
            :ignore-filter="true"
            :disabled="props.disabled"
            :open-on-focus="props.openOnFocus"
            :open-on-click="props.openOnClick"
        >
            <ComboboxAnchor :class="cn('w-full', props.classNames?.anchor)">
                <div class="relative flex w-full items-center">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <Search class="size-4 text-muted-foreground" />
                    </span>
                    <ComboboxInput
                        :id="props.id"
                        v-model="searchTerm"
                        :placeholder="props.placeholder"
                        :display-value="displayValue"
                        :aria-invalid="Boolean(props.error)"
                        :disabled="props.disabled"
                        :class="cn('pr-9 pl-9', props.allowClear && 'pr-11', props.classNames?.input)"
                        :autocomplete="props.autocomplete"
                    />
                    <span v-if="isLoading" class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground">
                        <LoaderCircle class="size-4 animate-spin" />
                    </span>
                    <button
                        v-else-if="showClearButton"
                        type="button"
                        aria-label="Clear selection"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground transition-colors hover:text-foreground"
                        @click.stop.prevent="clearSelection"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </ComboboxAnchor>

            <ComboboxList :class="props.classNames?.list">
                <template v-if="isLoading">
                    <ComboboxEmpty class="flex items-center justify-center gap-2">
                        <LoaderCircle class="size-4 animate-spin" />
                        <span>{{ props.loadingMessage }}</span>
                    </ComboboxEmpty>
                </template>

                <template v-else-if="fetchError">
                    <ComboboxEmpty>{{ fetchError }}</ComboboxEmpty>
                </template>

                <template v-else-if="!hasSufficientQuery">
                    <ComboboxEmpty>{{ props.noQueryMessage }}</ComboboxEmpty>
                </template>

                <template v-else-if="!options.length">
                    <slot name="empty" :query="trimmedSearch">
                        <ComboboxEmpty>{{ props.emptyMessage }}</ComboboxEmpty>
                    </slot>
                </template>

                <template v-else>
                    <ComboboxItem v-for="option in options" :key="getOptionKey(option)" :value="option" :class="props.classNames?.item">
                        <slot name="option" :option="option" :is-selected="isSelected(option)">
                            <div class="flex flex-col">
                                <span class="text-sm leading-tight font-medium">{{ option.label }}</span>
                                <span v-if="option.description" class="text-xs text-muted-foreground">
                                    {{ option.description }}
                                </span>
                            </div>
                        </slot>
                    </ComboboxItem>
                </template>

                <slot
                    name="after-options"
                    :query="trimmedSearch"
                    :has-results="options.length > 0"
                    :is-loading="isLoading"
                    :has-sufficient-query="hasSufficientQuery"
                />
            </ComboboxList>
        </Combobox>
    </FormField>
</template>
