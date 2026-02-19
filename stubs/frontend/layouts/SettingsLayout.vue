<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/style.utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    classNames?: {
        wrapper?: string;
    };
}>();

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: '/settings/profile',
    },
];

const page = usePage();
const currentPath = page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : '';
</script>

<template>
    <div class="flex h-full flex-col overflow-auto px-4 lg:flex-row lg:space-x-12 lg:overflow-hidden">
        <aside class="w-full max-w-xl py-6 lg:w-48">
            <nav class="flex flex-col space-y-1 space-x-0">
                <Button
                    v-for="item in sidebarNavItems"
                    :key="item.href"
                    variant="ghost"
                    :class="['w-full justify-start', { 'bg-muted': currentPath === item.href }]"
                    as-child
                >
                    <Link :href="item.href">
                        {{ item.title }}
                    </Link>
                </Button>
            </nav>
        </aside>

        <Separator class="my-6 lg:hidden" />

        <div class="flex-1 overflow-clip lg:size-full lg:overflow-auto">
            <div :class="cn('max-w-xl flex-1 py-6 md:max-w-2xl lg:max-w-3xl', props.classNames?.wrapper)">
                <slot />
            </div>
        </div>
    </div>
</template>
