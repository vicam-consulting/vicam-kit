## Inertia DTOs (Web/Frontend)

- **Location (MUST use):** `app/Data/Responses`
- **Purpose:** Enable Inertia.js partial reloads using `router.reload({ only: ['notes'] })`
- **Lazy types:** `Lazy::closure()`, `Lazy::inertia()`, `Lazy::inertiaDeferred()`
- **Once props:** `Inertia::once()` and middleware `shareOnce()` for data that loads once and persists
- **Attributes:** `#[AutoClosureLazy]`, `#[AutoInertiaLazy]`, `#[AutoWhenLoadedLazy]`
- **NEVER use:** `->include()` or `->exclude()` methods (not supported with Inertia lazy loading)
- **Documentation:** https://spatie.be/docs/laravel-data/v4/advanced-usage/use-with-inertia

## Inertia Lazy Loading for Partial Reloads

Lazy properties enable Inertia's partial reload feature. Frontend can request specific data:

```js
// Only reload the 'notes' prop from the server
router.reload({ only: ['notes'] })
```

**Lazy types:**
1. **`Lazy::closure()`** - Always included on first visit, optionally on partial reloads
2. **`Lazy::inertia()`** - Never included on first visit, optionally on partial reloads
3. **`Lazy::inertiaDeferred()`** - Included when ready (deferred), optionally on partial reloads

**Once props (Inertia 2.0):**
4. **`Inertia::once()`** - Resolved only on first visit, remembered by client, excluded on subsequent navigations

## Basic Patterns

<code-snippet name="Simple DTO with AutoClosureLazy" lang="php">
use Spatie\LaravelData\Attributes\AutoClosureLazy;

#[AutoClosureLazy]
class CaseIndexResponse extends Data
{
    public function __construct(
        public int $id,
        public Lazy|DecedentResponse $decedent,
    ) {}
}
</code-snippet>

<code-snippet name="DTO with AutoWhenLoadedLazy" lang="php">
use Spatie\LaravelData\Attributes\AutoWhenLoadedLazy;

class CaseNoteResponse extends Data
{
    public function __construct(
        public int $id,
        public ?string $text,
        #[AutoWhenLoadedLazy]
        /** @var Lazy|UserResponse */
        public Lazy|UserResponse $user,
    ) {}
}
</code-snippet>

<code-snippet name="Complex DTO with manual Lazy::closure()" lang="php">
class CaseShowResponse extends Data
{
    public function __construct(
        public int $id,
        /** @var Lazy|DataCollection<int, CaseNoteResponse> */
        public Lazy|DataCollection $notes,
    ) {}

    public static function fromModel(CaseRecord $case): self
    {
        return new self(
            id: $case->id,
            notes: Lazy::closure(fn () => 
                CaseNoteResponse::collect($case->notes, DataCollection::class)
            ),
        );
    }
}
</code-snippet>

## Once Props (Inertia 2.0)

`once` props are data that is:
- Resolved only on the **first page visit**
- **Remembered by the client** across subsequent navigations
- **Excluded from responses** when the client already has them
- **Automatically refreshed** when navigating away and returning, or when explicitly requested

### When to Use Once Props

**Good candidates:**
- Static lookup data (event types, venues, status options)
- Configuration/preferences that rarely change
- Expensive queries that don't need refreshing on form submissions
- Data used across multiple pages in a workflow

**When NOT to use:**
- Paginated data (always fresh, changes with filters/page)
- User-specific data that may change during session
- Real-time data (job statuses, notifications)
- Form submission results (must reflect latest state)

### Implementation Options

#### Option A: Per-Controller `Inertia::once()` (Recommended)

<code-snippet name="Controller with once props" lang="php">
return Inertia::render('cases/Show', [
    'case' => CaseShowResponse::from($case),
    'eventTypes' => Inertia::once(fn () => EventTypeResponse::collect(
        EventType::query()->where('is_active', true)->orderBy('sort_order')->get()
    )),
    'venues' => Inertia::once(fn () => VenueResponse::collect(
        Venue::query()->where('is_active', true)->withSchedules()->get()
    )),
]);
</code-snippet>

#### Option B: Middleware `shareOnce()` (For Global Data)

<code-snippet name="Middleware shareOnce" lang="php">
// app/Http/Middleware/HandleInertiaRequests.php

public function shareOnce(Request $request): array
{
    return [
        'appName' => fn () => config('app.name'),
        'eventTypes' => fn () => EventTypeResponse::collect(
            EventType::query()->where('is_active', true)->orderBy('sort_order')->get()
        ),
    ];
}
</code-snippet>

#### Option C: Combine with Deferred + Once

<code-snippet name="Deferred with once" lang="php">
// Load asynchronously on first visit, cache for subsequent visits
'venues' => Inertia::defer(fn () => VenueResponse::collect(...))->once(),
</code-snippet>

### Frontend: Force Refresh Once Props

<code-snippet name="Force refresh from frontend" lang="js">
// Force the server to re-resolve a once prop
router.reload({ only: ['eventTypes'] });
</code-snippet>

### First-Class Callable Syntax (PHP 8.1+)

For cleaner code, use first-class callable syntax instead of inline closures:

<code-snippet name="Callable syntax" lang="php">
return Inertia::render('Dashboard', [
    'stats' => Inertia::once($this->getStats(...)),
    'recentActivity' => Inertia::defer($this->getRecentActivity(...)),
]);

private function getStats(): DashboardStatsResponse { ... }
private function getRecentActivity(): DataCollection { ... }
</code-snippet>

## Controller Usage

<code-snippet name="Index with pagination" lang="php">
$cases = CaseRecord::query()
    ->with(['decedent']) // Eager load for lazy properties
    ->paginate(20);

return Inertia::render('cases/Index',
    CaseIndexResponse::collect($cases, PaginatedDataCollection::class)
);
</code-snippet>

<code-snippet name="Show page" lang="php">
$case = CaseRecord::query()->findOrFail($id);

return Inertia::render('cases/Show',
    CaseShowResponse::from($case)
);
</code-snippet>

## Pagination

ALWAYS pass paginator directly to DTO. Result includes `data`, `links`, and `meta` keys.

```php
YourResponse::collect($paginator, PaginatedDataCollection::class)
```

ALWAYS specify `PaginatedDataCollection::class` when collecting paginated results.

See: [From data to resource](https://spatie.be/docs/laravel-data/v4/as-a-resource/from-data-to-resource)

## Quick Reference

| Feature | Usage |
|---------|-------|
| **Location (MUST)** | `app/Data/Responses` |
| **Purpose** | Enable Inertia partial reloads: `router.reload({ only: ['notes'] })` |
| **Lazy types** | `Lazy::closure()`, `Lazy::inertia()`, `Lazy::inertiaDeferred()` |
| **Once props** | `Inertia::once()` for data resolved once and cached client-side |
| **Auto-lazy** | `#[AutoClosureLazy]`, `#[AutoInertiaLazy]`, `#[AutoWhenLoadedLazy]` |
| **Controller** | ALWAYS return DTO directly: `CaseResponse::from($case)` |
| **Pagination** | ALWAYS use `PaginatedDataCollection::class` |
| **NEVER use** | `->include()` or `->exclude()` (not supported with Inertia) |
| **Type order** | ALWAYS `Lazy\|Type` / NEVER `Type\|Lazy` |
| **Once candidates** | Static lookups (eventTypes, venues), config, expensive rarely-changing queries |

