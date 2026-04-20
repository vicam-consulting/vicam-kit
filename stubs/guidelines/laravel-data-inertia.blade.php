## Inertia DTOs (Web/Frontend)

- **Location (MUST use):** `app/Data/Responses`
- **Purpose:** enable Inertia partial reloads via `router.reload({ only: ['notes'] })`
- **Lazy types:** `Lazy::closure()`, `Lazy::inertia()`, `Lazy::inertiaDeferred()`
- **Once props:** `Inertia::once()` (per-controller) or `shareOnce()` (middleware, global)
- **Attributes:** `#[AutoClosureLazy]`, `#[AutoInertiaLazy]`, `#[AutoWhenLoadedLazy]`
- **NEVER use** `->include()` / `->exclude()` — unsupported with Inertia lazy loading.
- **Type order:** ALWAYS `Lazy|Type`, NEVER `Type|Lazy`. Same for `Lazy|DataCollection<...>` in PHPDoc.
- **Enums:** type properties as the `BackedEnum` itself (`public ItemStatus $status`). Inertia v3 serializes natively; the TS transformer emits enum unions. Do not hand-serialize with `->value`.
- **Docs:** https://spatie.be/docs/laravel-data/v4/advanced-usage/use-with-inertia

## Lazy Type Semantics

- `Lazy::closure()` — included on first visit; optional on partial reloads.
- `Lazy::inertia()` — excluded on first visit; optional on partial reloads.
- `Lazy::inertiaDeferred()` — deferred-loaded; optional on partial reloads.
- `Inertia::once()` — first visit only; client remembers, server excludes after.

## DTO Patterns

Use `#[AutoClosureLazy]` on the class for a default, and `#[AutoWhenLoadedLazy]` on individual relationship props that should only serialize when eager-loaded. For complex shapes (e.g. collections needing `DataCollection::class`), use a `fromModel()` static with `Lazy::closure(fn () => ...)`.

<code-snippet name="DTO with lazy + collection" lang="php">
class ItemShowResponse extends Data
{
    public function __construct(
        public int $id,
        /** @var Lazy|DataCollection<int, ItemNoteResponse> */
        public Lazy|DataCollection $notes,
    ) {}

    public static function fromModel(Item $item): self
    {
        return new self(
            id: $item->id,
            notes: Lazy::closure(fn () =>
                ItemNoteResponse::collect($item->notes, DataCollection::class)
            ),
        );
    }
}
</code-snippet>

## Once Props

Resolved once on first visit, remembered client-side, excluded from later responses, refreshed when navigating away and back (or via `router.reload({ only: [...] })`).

- **Use for:** static lookups (statuses, categories, types), rarely-changing config, expensive shared-workflow queries.
- **Do NOT use for:** paginated data, user state that may change mid-session, real-time data (job statuses, notifications), form results.
- **Per-controller:** `'categories' => Inertia::once($this->getCategories(...))` — preferred for page-scoped data.
- **Global:** implement `shareOnce()` in `HandleInertiaRequests` middleware for cross-page shared data.
- **Deferred + once:** `Inertia::defer($this->getCategories(...))->once()` — load async on first visit, then cache.
- See Inertia v3 docs for `once` prop behavior.

## Preserving Validation Errors on Partial Reloads

Inertia v3's `preserveErrors: true` keeps the `errors` bag across partial reloads — use on any `router.visit`/`router.reload` that runs while validation errors must stay visible (e.g. inline-save failure that refreshes a table). Pair with `preserveState: true` / `preserveScroll: true` as needed.

## Controller Usage

- Return DTOs directly: `return Inertia::render('items/Show', ItemShowResponse::from($item))`.
- Paginate: `ItemIndexResponse::collect($paginator, PaginatedDataCollection::class)` — yields `data`, `links`, `meta`.
- ALWAYS pass `PaginatedDataCollection::class` when collecting paginated results.
- Eager-load relationships used by lazy properties (`->with([...])`) to avoid N+1.

See: https://spatie.be/docs/laravel-data/v4/as-a-resource/from-data-to-resource
