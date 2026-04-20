<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { useDebounceFn } from '@vueuse/core';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
    }>(),
    {
        modelValue: '',
        placeholder: 'Search Cases',
    },
);

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

const inputValue = ref(props.modelValue);

watch(
    () => props.modelValue,
    (nextValue) => {
        if (nextValue !== inputValue.value) {
            inputValue.value = nextValue;
        }
    },
);

const emitDebouncedValue = useDebounceFn((value: string) => {
    emit('update:modelValue', value);
}, 300);

watch(inputValue, (nextValue) => {
    emitDebouncedValue(nextValue);
});
</script>

<template>
    <div class="relative w-full max-w-sm items-center">
        <Input id="search" v-model="inputValue" type="text" :placeholder="placeholder" class="pl-10" />
        <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
            <Search class="size-6 text-muted-foreground" />
        </span>
    </div>
</template>
