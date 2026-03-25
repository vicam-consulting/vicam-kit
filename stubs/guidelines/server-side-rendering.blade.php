# Server-Side Rendering (SSR)

This application is deployed with **Inertia SSR enabled**. All frontend code must be SSR-compatible.

## Deployment

- Production builds **must** use `npm run build:ssr` (runs `vite build && vite build --ssr`).
- The SSR entry point is `resources/js/ssr.ts`, using `createSSRApp` and `renderToString`.
- Clustering is enabled (`{ cluster: true }`) for multi-core request handling.
- The SSR server is managed via `php artisan inertia:start-ssr` / `php artisan inertia:stop-ssr`.

## Browser APIs Are Unavailable During SSR

During SSR, code runs in **Node.js**. These globals do **not exist** on the server and will crash the SSR process if accessed at module scope:

`window`, `document`, `navigator`, `localStorage`, `sessionStorage`, `HTMLElement`, `IntersectionObserver`, `ResizeObserver`, `MutationObserver`, `requestAnimationFrame`, `matchMedia`, `getComputedStyle`, `history`, `location`, `alert`, `confirm`, `prompt`

## Safe Patterns

### Use `onMounted` for Browser Logic

`onMounted` only runs in the browser — put all browser-dependent logic here:

<code-snippet name="onMounted pattern" lang="vue">
<script setup lang="ts">
import { onMounted, ref } from 'vue';

const width = ref(0);

onMounted(() => {
    width.value = window.innerWidth;
});
</script>
</code-snippet>

### Guard Browser APIs in Composables / Utilities

<code-snippet name="Browser guard" lang="ts">
const isBrowser = typeof window !== 'undefined';

if (isBrowser) {
    // safe to use window, document, etc.
}
</code-snippet>

### Dynamic Imports for Browser-Only Libraries

Libraries that access the DOM at import time (e.g., signature pads, chart libraries, WYSIWYG editors) must be dynamically imported:

<code-snippet name="Dynamic import" lang="vue">
<script setup lang="ts">
import { defineAsyncComponent } from 'vue';

const SignaturePad = defineAsyncComponent(() => import('@/components/SignaturePad.vue'));
</script>
</code-snippet>

### Client-Only Rendering with `v-if`

<code-snippet name="Client-only rendering" lang="vue">
<script setup lang="ts">
import { onMounted, ref } from 'vue';

const isMounted = ref(false);
onMounted(() => { isMounted.value = true; });
</script>

<template>
    <div v-if="isMounted">
        <!-- Browser-only content -->
    </div>
</template>
</code-snippet>

## Common Pitfalls

- **Third-party libraries** — Verify new libraries are SSR-safe or use dynamic imports.
- **Global event listeners** — Add in `onMounted`, clean up in `onUnmounted`. Never at module scope.
- **Template refs** — Are `null` during SSR. Only access inside `onMounted` or later.
- **`watch` with `immediate: true`** — Guard any browser API calls inside the watcher callback.
- **Pinia stores / composables** — Defer browser API access to `onMounted` or guard with `typeof window`.

## Selective SSR Disabling (Last Resort)

<code-snippet name="Disable SSR per route" lang="php">
// In a middleware or controller
config(['inertia.ssr.enabled' => false]);
</code-snippet>

Fix the component to be SSR-compatible whenever possible instead of disabling SSR.
