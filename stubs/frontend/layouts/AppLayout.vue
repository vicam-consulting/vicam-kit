<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/sidebar/AppSidebar.vue';
import AppSidebarHeader from '@/components/sidebar/AppSidebarHeader.vue';
import { cn } from '@/lib/style.utils';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    classes?: {
        content?: string;
    };
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell>
        <AppSidebar />
        <AppContent class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs">
                <slot name="actions" />
            </AppSidebarHeader>

            <div class="relative max-h-full flex-1 overflow-hidden">
                <div :class="cn('size-full overflow-x-hidden overflow-y-auto p-4', classes?.content)">
                    <slot />
                </div>
            </div>
        </AppContent>
    </AppShell>
</template>
