=== vue/core rules ===

## Vue.js Best Practices

This project uses Vue 3 with TypeScript, Inertia.js, and shadcn/ui components.

## Component Syntax

### Script Setup
- **Always use `<script setup lang="ts">` syntax exclusively** - never use Options API or regular script tags
- **Include proper TypeScript interfaces for props** - prefer generated interfaces from the `resources/js/types/generated.ts` file
 - **Prefer generated DTO types** from `resources/js/types/generated.ts` for Inertia props. Avoid hand-rolled types when a `...Response` DTO exists.
 - **Import types directly** using the short alias: `import type { EventTypeResponse } from '@/types/generated'` NOT `App.Data.Responses.EventTypeResponse`
 - **Naming:** Input DTOs are suffixed with `Request` and outputs with `Response` (e.g., use `UserResponse` for page props). Validate inputs server-side via laravel-data Request DTOs.

<code-snippet name="Component Structure Example" lang="vue">
<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItem[];
    title: string;
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <!-- Component template -->
</template>
</code-snippet>

## Shadcn/UI Components

### Adding New Components
- **Add new shadcn components**: `npx shadcn-vue@latest add [component-name]` (Vue version, not React)
- **Use shadcn/ui components as building blocks** - always check available shadcn components before creating custom ones
- Import shadcn components from `@/components/ui/` directory

<code-snippet name="Shadcn Component Usage" lang="vue">
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
</script>
</code-snippet>

## Styling & Layout

### Dark Mode Support
- **Make sure to maintain support for dark mode** - use `dark:` prefixes for all styled elements
- Follow existing styles/patterns in the codebase

<code-snippet name="Dark Mode Example" lang="vue">
<template>
    <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
        <h2 class="text-neutral-900 dark:text-neutral-100">
            Title
        </h2>
    </div>
</template>
</code-snippet>

## Dates & Time

- Use the datetime utilities in `resources/js/lib/datetime.ts` for consistency:
  - Use `utcToLocal(isoUtc)` when displaying UTC timestamps to users.
  - Use `nowLocal()` to prefill `datetime-local` inputs.
  - Use `localToUtc(value)` to convert local input values to UTC before submitting forms.

## Inertia Integration

### WhenVisible Component

Use the `WhenVisible` component to lazy load content that appears below the fold:

<code-snippet name="WhenVisible for lazy loading" lang="vue">
<script setup lang="ts">
import { WhenVisible } from '@inertiajs/vue3';
</script>

<template>
    <!-- Hero content loads immediately -->
    <HeroSection :data="heroData" />

    <!-- Stats only load when scrolled into view -->
    <WhenVisible data="statistics" fallback="Loading...">
        <template #default="{ statistics }">
            <StatsSection :stats="statistics" />
        </template>
    </WhenVisible>
</template>
</code-snippet>

Combine with deferred props on the backend for optimal performance.

### Force Refresh Props

To force the server to re-resolve a prop (including `once` props):

<code-snippet name="Force refresh props" lang="ts">
import { router } from '@inertiajs/vue3';

// Reload specific props from the server
router.reload({ only: ['eventTypes', 'venues'] });
</code-snippet>

### Layout Components
- **Use appropriate layout components** - leverage existing layout components for page structure
- Structure pages with proper layout hierarchy

<code-snippet name="Layout Usage Example" lang="vue">
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Page content -->
    </AppLayout>
</template>
</code-snippet>
