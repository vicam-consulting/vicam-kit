# Multitenancy (Path-Based)

Uses `spatie/laravel-multitenancy` with **path-based** tenancy (URL prefix `/{tenant}/`, not subdomains). Single shared database; isolation via `tenant_id` foreign keys + query scoping.

## Core Concepts

- **`Tenant::current()`** is the single source of truth for active tenant context. NEVER use the user's stored tenant preference (e.g. `$user->last_tenant_id`) for tenant resolution.
- The user's stored tenant preference is for **post-login redirect only** (sends user to `/{last-tenant-slug}/dashboard`).

## Routes

- Public routes: no prefix.
- `/dashboard`: named `dashboard.redirect`, resolves the user's default tenant and redirects to `/{slug}/dashboard`.
- Tenant-scoped routes live under `Route::middleware(['auth','verified','tenant'])->prefix('{tenant}')->group(...)`.

## `tenant` Middleware (`SetTenantFromPath`)

Registered in `bootstrap/app.php` as `'tenant' => SetTenantFromPath::class`. On each request it:
1. Resolves `{tenant}` by slug
2. Validates the authenticated user is a tenant member (403 if not)
3. Calls `$tenant->makeCurrent()`
4. Updates the user's stored tenant preference if they switched tenants
5. Calls `forgetParameter('tenant')` so the Tenant model is **not** injected as the first controller arg

## `BelongsToTenant` Trait

Location: `App\Models\Concerns\BelongsToTenant`. Apply to any model with a `tenant_id` column that should be isolated per tenant. Provides:
- Global scope filtering by `Tenant::current()` (no-op when no tenant active)
- Auto-assigns `tenant_id` on create
- `scopeWithoutTenantScope()` — cross-tenant queries
- `scopeForTenant(Tenant $tenant)` — query a specific tenant
- `tenant()` BelongsTo relationship

Do **not** apply to models reached through already-scoped parents (e.g. line items go through an already-scoped order).

## Controllers, Policies, Actions

- **Controllers** inside the `{tenant}` group: use `Tenant::current()` for tenant logic. With `BelongsToTenant`, explicit `where('tenant_id', ...)` is only needed for special cases.
- **Policies:** use null-safe `Tenant::current()?->id` for ownership checks — decouples authz from the user's stored tenant preference.
- **Actions:** tenant-scoped creates use `Tenant::current()->id`. For flows that may lack tenant context (public endpoints, webhooks), fall back: `Tenant::current()?->id ?? $parent->tenant_id`.

## Queued Jobs

Tenant-aware by default (`queues_are_tenant_aware_by_default: true` in `config/multitenancy.php`). Spatie captures `Tenant::current()->id` at dispatch and restores it before `handle()`. No special trait or interface needed — `ShouldQueue` + `Queueable` is sufficient.

**Critical gotcha — public routes:** jobs dispatched from public (non-tenant-prefixed) routes have no `tenant` middleware. Call `$model->tenant->makeCurrent()` inside the action **before** dispatching events/jobs.

## Cache Isolation

`PrefixCacheTask` (in `switch_tenant_tasks`) auto-prefixes cache keys with `tenant_id_{id}` when a tenant is active. No manual prefixing needed.

## Frontend

Get current tenant slug from Inertia shared props (e.g. `page.props.auth?.currentTenant?.slug`). Pass to Wayfinder route helpers. Tenant switching: `router.visit('/{slug}/dashboard')` — no POST endpoint.

## Auth Redirects

Login, registration, and invitation acceptance redirect to `route('dashboard.redirect')` (the `/dashboard` endpoint), which resolves the user's default tenant and redirects to `/{slug}/dashboard`.

## Testing

- Add a `tenantUrl(Tenant $tenant, string $path)` helper in `tests/Pest.php` for tenant-scoped test URLs.
- Call `$tenant->makeCurrent()` before any operation that reads `Tenant::current()` — job dispatch, policy checks, model creation via `BelongsToTenant`.
- Call `Tenant::forgetCurrent()` in `afterEach` to prevent tenant state leaking between tests.
- Cross-tenant access returns **403** (policy denies), not 404 (scope hides), because route model binding resolves by ID regardless of scope.
- `BelongsToTenant` creating hook auto-assigns `tenant_id` — if you set an explicit `tenant_id` on a factory, call `makeCurrent()` AFTER creating fixtures so it isn't overwritten.

<code-snippet name="Job test tenant setup" lang="php">
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->defaultTenant->makeCurrent();
});

afterEach(function () {
    Tenant::forgetCurrent();
});
</code-snippet>
