# Server-Side Rendering (SSR)

Deployed with **Inertia v3 SSR enabled**. All frontend code must be SSR-compatible.

## Deployment

- Production build: `npm run build` (runs `vite build && vite build --ssr`).
- SSR entry: `resources/js/ssr.ts` using `createSSRApp` + `renderToString`, clustering enabled (`{ cluster: true }`).
- Client entrypoints (`app.ts`, etc.) MUST use `createSSRApp(...).mount(el)` — never `createApp` — so server HTML is **hydrated**, not re-rendered.
- Root Blade uses v3 components: `<x-inertia::head />` and `<x-inertia::app />` (replaces `@inertiaHead` / `@inertia`).
- Production process managed via `php artisan inertia:start-ssr` / `inertia:stop-ssr`. Development: `@inertiajs/vite` handles SSR automatically via `npm run dev` — no separate process.

## Browser APIs Are Unavailable During SSR

During SSR, code runs in **Node.js**. The following do NOT exist on the server and will crash SSR if accessed at module scope:

`window`, `document`, `navigator`, `localStorage`, `sessionStorage`, `HTMLElement`, `IntersectionObserver`, `ResizeObserver`, `MutationObserver`, `requestAnimationFrame`, `matchMedia`, `getComputedStyle`, `history`, `location`, `alert`, `confirm`, `prompt`.

## Safe Patterns

- **`onMounted(() => { ... })`** — only runs in the browser. Put all browser-dependent logic here (e.g. reading `window.innerWidth`).
- **Guard in composables/utilities:** `const isBrowser = typeof window !== 'undefined'; if (isBrowser) { ... }`.
- **Dynamic imports** for browser-only libraries that access the DOM at import time (signature pads, chart libs, WYSIWYG editors): `defineAsyncComponent(() => import('@/components/SignaturePad.vue'))`.
- **Client-only rendering:** `const isMounted = ref(false); onMounted(() => isMounted.value = true);` then `<div v-if="isMounted">...</div>`.

## Hydration Mismatches — Fix the Right Layer

1. **Structural / branching mismatches (MUST fix render path)** — mobile vs desktop markup, `matchMedia`, sidebar/sheet branching, cookie-driven layout differences. Server and first client render MUST produce the **same DOM tree**. Defer viewport / browser-state branching until `onMounted`.
2. **Text-only mismatches (use `data-allow-mismatch="text"`)** — browser-local date/time formatting, `toLocaleString()`, timezone-specific timestamps. Put it on the exact text container (e.g. `<span data-allow-mismatch="text">@{{ utcToLocal(timestamp) }}</span>`) rather than scattering `hydrated` refs through components.
3. **If local browser time isn't required**, prefer deterministic output — format in a canonical timezone on the backend or render a server-stable string.

## Common Pitfalls

- **Third-party libraries** — verify SSR-safety or dynamic-import them.
- **Global event listeners** — add in `onMounted`, clean up in `onUnmounted`. Never at module scope.
- **Template refs** — `null` during SSR; access only inside `onMounted` or later.
- **`watch` with `immediate: true`** — guard any browser API calls inside the callback.
- **Pinia stores / composables** — defer browser API access to `onMounted` or guard with `typeof window`.
- **Timezone / locale formatting** — browser-local formatting differs SSR vs hydration; for text-only differences use `data-allow-mismatch="text"`.
- **Media-query branching in render** — do NOT let SSR render desktop markup while the first client render picks mobile. Render one stable tree first, switch after mount.

## Selective SSR Disabling (Last Resort)

`config(['inertia.ssr.enabled' => false])` in a middleware/controller disables SSR for that request. Prefer fixing the component to be SSR-compatible whenever possible.
