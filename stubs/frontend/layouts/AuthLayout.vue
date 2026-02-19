<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex h-svh flex-col items-center justify-center gap-6 overflow-y-auto bg-muted p-2 sm:p-6 md:p-10">
        <div class="flex w-full max-w-md flex-col gap-6">
            <Card class="rounded-none border-0 bg-content sm:rounded-xl sm:border" variant="filled">
                <CardHeader class="px-10 pt-8 pb-0 text-center">
                    <CardTitle class="text-xl">{{ title }}</CardTitle>
                    <CardDescription>
                        {{ description }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="px-10 py-8">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
