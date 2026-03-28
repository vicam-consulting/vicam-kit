# Multitenancy (Path-Based)

This application uses **path-based tenancy** via `spatie/laravel-multitenancy`. Tenants are identified by URL path prefix (`/{tenant}/`), not subdomains.

## Core Concepts

- **`Tenant::current()`** is the single source of truth for the active tenant context. Never use the user's stored tenant preference for tenant resolution — that is only for post-login redirect.
- **Single database** — all tenants share one database. Isolation is achieved via `tenant_id` foreign keys and query scoping.

## Route Structure

All tenant-scoped routes use a `{tenant}` prefix resolved by slug:

<code-snippet name="Route structure" lang="php">
// routes/web.php

// Public routes — no tenant prefix
Route::get('/', [MarketingController::class, 'homepage']);

// Dashboard redirect — resolves user's default tenant
Route::middleware(['auth', 'verified'])->get('dashboard', function () {
    $tenant = request()->user()->getDefaultTenant();
    return redirect()->route('dashboard', ['tenant' => $tenant->slug]);
})->name('dashboard.redirect');

// Tenant-scoped routes
Route::middleware(['auth', 'verified', 'tenant'])->prefix('{tenant}')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    // ...
});
</code-snippet>

The `tenant` middleware should:
1. Resolve the `{tenant}` route parameter by slug
2. Validate the authenticated user is a member (403 if not)
3. Call `$tenant->makeCurrent()` to activate tenant context
4. Update the user's last-used tenant if they switched
5. Remove the `{tenant}` parameter via `forgetParameter('tenant')` so it doesn't inject into controller method signatures

## Tenant Middleware

<code-snippet name="Middleware behavior" lang="php">
// Registered in bootstrap/app.php as:
'tenant' => SetTenantFromPath::class

// Applied to the {tenant} prefix group — automatically activates tenant context.
// Controllers within this group use Tenant::current() instead of $user->lastTenant.
</code-snippet>

**Important:** `forgetParameter('tenant')` prevents Laravel's ControllerDispatcher from injecting the Tenant model as the first positional argument to controller methods.

## `BelongsToTenant` Trait

Apply `use BelongsToTenant` to any Eloquent model that is tenant-scoped. The trait provides:

- **Global scope** — automatically filters queries by `Tenant::current()` (no-op when no tenant is active)
- **Auto-assign `tenant_id`** — on model creation, sets `tenant_id` from `Tenant::current()` if present
- **`scopeWithoutTenantScope()`** — removes the global scope for cross-tenant queries
- **`scopeForTenant(Tenant $tenant)`** — queries for a specific tenant regardless of current context
- **`tenant()` relationship** — `BelongsTo` relationship back to the Tenant model

<code-snippet name="Applying BelongsToTenant" lang="php">
use App\Models\Concerns\BelongsToTenant;

class Invoice extends Model
{
    use BelongsToTenant;
    // Model now automatically scoped to Tenant::current()
}
</code-snippet>

### When to Apply

Apply `BelongsToTenant` to models that have a `tenant_id` column and should be isolated per tenant.

Do **not** apply to models accessed via relationships through already-scoped parents (e.g., `InvoiceLineItem` is accessed through `Invoice`, which is already scoped).

## Controllers

Controllers within the `{tenant}` route group use `Tenant::current()` for all tenant-related logic:

<code-snippet name="Controller pattern" lang="php">
// CORRECT — use Tenant::current()
$invoices = Invoice::query()
    ->where('tenant_id', Tenant::current()->id)
    ->paginate(20);

// WRONG — do not use auth user for tenant resolution
$invoices = Invoice::query()
    ->where('tenant_id', $request->user()->last_tenant_id)
    ->paginate(20);
</code-snippet>

Note: With `BelongsToTenant`, the global scope handles filtering automatically — explicit `where('tenant_id', ...)` is only needed in special cases.

## Policies

Policies use `Tenant::current()?->id` (null-safe) for ownership checks:

<code-snippet name="Policy pattern" lang="php">
public function view(User $user, Invoice $invoice): bool
{
    return $invoice->tenant_id === Tenant::current()?->id;
}
</code-snippet>

This decouples authorization from the authenticated user's stored tenant preference.

## Actions

Actions that create tenant-scoped models use `Tenant::current()->id`. For actions that may run without tenant context (e.g., public-facing flows), use a fallback:

<code-snippet name="Action pattern with fallback" lang="php">
// For authenticated, tenant-scoped flows
$invoice->tenant_id = Tenant::current()->id;

// For flows that may lack tenant context (public forms, webhooks)
$invoice->tenant_id = Tenant::current()?->id ?? $parentModel->tenant_id;
</code-snippet>

## Queued Jobs

All queued jobs are tenant-aware by default (`queues_are_tenant_aware_by_default: true` in `config/multitenancy.php`). When a job is dispatched within a tenant context, Spatie automatically:

1. Captures `Tenant::current()->id` at dispatch time
2. Restores `Tenant::current()` before the job's `handle()` method runs

No special traits or interfaces needed on job classes — `ShouldQueue` + `Queueable` is sufficient.

**Critical:** Jobs dispatched from public routes (webhooks, public forms) require manual tenant context setup because these routes have no `{tenant}` prefix and no `tenant` middleware. Set tenant context in the action before dispatching events/jobs:

<code-snippet name="Public route tenant setup" lang="php">
// In an action handling a public/webhook flow:
$parentModel->tenant->makeCurrent();
// Now any dispatched jobs will capture this tenant context
</code-snippet>

## Cache Isolation

`PrefixCacheTask` (configured in `switch_tenant_tasks`) automatically prefixes cache keys with `tenant_id_{id}` when a tenant is active. This ensures cached data is isolated between tenants without any manual key prefixing.

## Frontend

The frontend gets the current tenant slug from Inertia shared props:

<code-snippet name="Frontend tenant slug" lang="ts">
const page = usePage();
const tenantSlug = computed(() => page.props.auth?.tenant?.slug ?? '');

// Use in route generation
route('items.index', { tenant: tenantSlug.value })
</code-snippet>

Tenant switching uses `router.visit('/{slug}/dashboard')` — no POST endpoint needed.

## Auth Flow Redirects

Login, registration, and invitation acceptance redirect to a dashboard redirect route, which resolves the user's default tenant and redirects to `/{tenant-slug}/dashboard`.

## Testing

- **`tenantUrl(Tenant $tenant, string $path)`** helper generates `/{tenant->slug}{$path}` — use for all tenant-scoped test URLs
- **`$tenant->makeCurrent()`** must be called before any operation that reads `Tenant::current()` — including job dispatch, policy checks, and model creation via `BelongsToTenant`
- **`Tenant::forgetCurrent()`** must be called in `afterEach` to prevent tenant state leaking between tests
- **Cross-tenant access** returns 403 (policy denies), not 404 (scope hides), because route model binding resolves by ID regardless of tenant scope
- **`BelongsToTenant` creating hook** auto-assigns `tenant_id` — set `makeCurrent()` AFTER creating test fixtures to avoid overwriting explicit `tenant_id` values

<code-snippet name="Test setup" lang="php">
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = $this->user->defaultTenant;
    $this->tenant->makeCurrent();
});

afterEach(function () {
    Tenant::forgetCurrent();
});
</code-snippet>
