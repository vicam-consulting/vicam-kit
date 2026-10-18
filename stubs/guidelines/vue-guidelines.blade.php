=== vue/core rules ===

## Vue.js Best Practices

Vue 3 + TypeScript + Inertia v3 + shadcn/ui.

## Component Syntax

- Always `<script setup lang="ts">`; never Options API.
- Prefer generated DTO types from `resources/js/types/generated.ts` — avoid hand-rolled types when a `...Response` DTO exists.
- Import as `import type { ItemResponse } from '@/types/generated'` — NOT `App.Data.Responses.ItemResponse`.
- Naming: input DTOs end `Request`, outputs end `Response` (use `UserResponse` for page props).

## Shadcn/UI

- Add components with `npx shadcn-vue@latest add [name]` (Vue, not React).
- Check `@/components/ui/` before building custom — shadcn first.

## Styling

- Maintain dark mode on every styled element via `dark:` prefixes.
- Follow existing patterns in the codebase.

## Dates & Time

If the application provides `resources/js/lib/datetime.utils.ts`, use:
- `utcToLocal(isoUtc)` — display UTC timestamps.
- `nowLocal()` — prefill `datetime-local` inputs.
- `localToUtc(value)` — convert local input values to UTC before submit.

## Page Titles

Use Inertia's `<Head>`.

- **Why:** purpose-built for `<title>`/`<meta>`, SSR-safe (no duplicate title tags), and composes with the app-name suffix configured in `createInertiaApp`'s `title` callback (`resources/js/app.ts`).
- Static: `<Head title="Dashboard" />`. Dynamic: `<Head :title="item?.name ?? 'Item'" />`.
- Keep `<Head>` in the **page component**, never in a shared layout component.

## Inertia v3 Integration

- **`useHttp`** — form-style XHR that does **not** navigate (dialog fetches, receipt submissions). Returns `{ processing, wasSuccessful, errors, get/post/put }` without mutating page props. See Inertia v3 docs.
- **`router.optimistic(fn).put(...)`** — apply expected page-prop shape immediately; Inertia reconciles on success and auto-rolls-back on error. Use for inline edits where the optimistic shape is obvious.
- **Instant visits** — `<Link :href :component="pageName">` mounts the target before the response, with only shared props. Use only where that page renders sensibly without its own props.
- **Deferred props** — render a same-size `Skeleton` while the prop is `undefined` (`<Deferred data="key">` with a `#fallback` slot, or a `v-if` on `undefined`).
- **`<WhenVisible data="key">`** (with a `#fallback` slot) — loads an `Inertia::optional()` prop when scrolled into view.
- **`router.reload({ only: ['categories'] })`** — force the server to re-resolve specific props (including `once` props).
- **`usePoll(ms, { only: [...], preserveErrors: true }, { mode: 'rest' })`** — always name the props. The third argument takes poll options: `mode` (`'rest'` waits for each response before the next interval) needs `@inertiajs/vue3` 3.2+, `background: 'pause'` 3.9+; check `package.json`.

## Layouts

- Pages inside the authenticated shell import a layout component (e.g. `AppLayout`) and wrap their template: `<AppLayout :breadcrumbs="breadcrumbs">...</AppLayout>`. Pass layout state (breadcrumbs, etc.) as a direct prop.
- Prefer declaring props like `breadcrumbs?: BreadcrumbItem[]` via `defineProps` on the layout component rather than relying on Inertia v3's `setLayoutProps()` / `useLayoutProps()` — direct props are simpler when every shell page already wraps its layout explicitly.
- Opt-out page trees (auth, errors, public signing, marketing, etc.) own their own layout or none. Do not register a default layout in `createInertiaApp` — each page explicitly wraps what it needs.
- Nested layouts (e.g. `SettingsLayout`) render inside `<AppLayout>` in the page template.
