<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

import { Button } from './ui/button';

const props = defineProps<{
    perPage: number;
    total: number;
    currentPage: number;
    lastPage: number;
    onClickNext: () => void;
    onClickPrevious: () => void;
}>();

const isFirstPage = computed(() => props.currentPage === 1);
const isLastPage = computed(() => props.currentPage === props.lastPage);
</script>

<template>
    <div
        v-if="total > perPage"
        class="flex w-full items-center justify-end gap-4"
    >
        <div class="flex items-center gap-4">
            <span class="text-sm"
                >Page {{ currentPage }} of {{ lastPage }}</span
            >
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    :disabled="isFirstPage"
                    @click="onClickPrevious"
                >
                    <ChevronLeft />
                </Button>
                <Button
                    variant="outline"
                    :disabled="isLastPage"
                    @click="onClickNext"
                >
                    <ChevronRight />
                </Button>
            </div>
        </div>
    </div>
</template>
