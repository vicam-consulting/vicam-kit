<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuBadge, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItemGroup } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    groups: NavItemGroup[];
}>();

const page = usePage();

/**
 * Check if a navigation item is active by comparing the URL path (ignoring query params)
 */
const isActive = (href: string) => {
    const currentPath = page.url.split('?')[0];
    const itemPath = href.split('?')[0];
    return currentPath === itemPath;
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <template v-for="group in groups" :key="group.title">
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel>{{ group.title }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in group.items" :key="item.title">
                        <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.title">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                        <SidebarMenuBadge v-if="item.badge && item.badge > 0" class="bg-destructive text-destructive-foreground!">
                            {{ item.badge }}
                        </SidebarMenuBadge>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </template>
    </div>
</template>
