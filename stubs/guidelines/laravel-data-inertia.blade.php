## Inertia DTOs (Web/Frontend)

- **Location (MUST use):** `app/Data/Responses`. Page props are Response DTOs: either the root props (`Inertia::render('items/Show', ItemShowResponse::from($item))`) or a keyed array whose values are Response DTOs, DTO collections, prop types wrapping them (`fn () => ...`, `Inertia::defer(...)`, `Inertia::once(...)`) or plain scalars and small filter echoes. Never pass Eloquent models (they serialize every visible column) or ad-hoc nested arrays.
- **Type order:** ALWAYS `Lazy|Type`, NEVER `Type|Lazy`. Same for `Lazy|DataCollection<...>` in PHPDoc.
- **Enums:** type properties as the `BackedEnum` itself (`public ItemStatus $status`). Inertia v3 serializes natively; the TS transformer emits enum unions. Do not hand-serialize with `->value`.
- **Docs:** https://spatie.be/docs/laravel-data/v4/advanced-usage/use-with-inertia and https://inertiajs.com/docs/v3 (partial reloads, deferred, once and merge props).

## Choosing a Prop Type

| Need | Use | First visit | Partial reload |
|---|---|---|---|
| Page data | closure `fn () => ...` / `Lazy::closure()` | included | only when requested |
| Must refresh on every response (cheap, e.g. `auth`) | `Inertia::always()` | included | always |
| Only on request (`router.reload({ only })`) | `Inertia::optional()` | excluded | only when requested |
| Slow or non-critical; the page renders without it | `Inertia::defer()` / `Lazy::inertiaDeferred()` | follow-up request | only when requested |
| Doesn't change during a session (config, lookups) | `Inertia::once()` / `shareOnce()` | included, then the client keeps it | only when requested |

- **Closures by default.** Wrap page preparation (queries, eager loads) in prop closures, or load inside them, so partial reloads (polling, deferred follow-ups, `only` visits) skip it. A deferred prop re-runs the controller, so eager work there runs twice.
- **`always` is costly:** it runs on every poll and deferred follow-up. Keep it to cheap data that must never be stale.
- **Deferred:** every deferred prop needs a placeholder (pulsing `Skeleton`) the same size as the content, so nothing jumps. Shared deferred props load on every visit; keep them few.
- **Once:** the client keeps the value while it visits pages that share the prop; explicit `only` requests and full page loads re-resolve it. If the value derives from changing state, key it with `->as('name.'.$stateKey)` so a change gives a new key. Don't use once for counts, usage, paginated data or anything a form changes.
- **Merge:** `Inertia::merge()` / `deepMerge()` only for append-style lists (load more, infinite scroll); full visits replace.

## laravel-data Lazy Props with Inertia v3

- `Lazy::closure()` / `#[AutoClosureLazy]` resolve to a plain closure: Inertia's lazy evaluation (included on full visits, optional on partial reloads).
- `Lazy::inertiaDeferred()` / `#[AutoInertiaDeferred]` resolve to `Inertia\DeferProp`.
- `Lazy::inertia()` / `#[AutoInertiaLazy]` need laravel-data **4.22.1+** on Inertia v3 (resolves to `Inertia\OptionalProp`); older versions build `Inertia\LazyProp`, which v3 removed (fatal "class not found"). In the controller's props array prefer `Inertia::optional()`.
- **NEVER use `Lazy::create()`, `->include()` or `->exclude()` in Inertia DTOs:** those are laravel-data's own partials for API resources. A `Lazy::create()` prop is simply never sent; Inertia's `only` / `except` headers do the filtering.
- Auto-lazy attributes only apply when laravel-data builds the object (`::from()` without a custom `fromModel()`). A `fromModel()` that calls `new self(...)` ignores them; pass `Lazy::closure()` / `Lazy::whenLoaded()` explicitly there.
- Lazies only filter at the top level of the props. A DTO nested inside another prop resolves with its parent, so `only: ['item.notes']` still builds all of `item`.
- `#[AutoWhenLoadedLazy]` / `Lazy::whenLoaded()` for relationships that should only serialize when eager-loaded.

<code-snippet name="Response DTO with lazy collection" lang="php">
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
            notes: Lazy::closure(fn () => ItemNoteResponse::collect($item->notes, DataCollection::class)),
        );
    }
}
</code-snippet>

## Partial Reloads and Polling

- Name the props: `router.reload({ only: ['item'] })`, `usePoll(5000, { only: ['item'], preserveErrors: true })`. A poll without `only` re-runs the whole page.
- `preserveErrors: true` keeps the `errors` bag across partial reloads (always-props would otherwise clear it).
- After a mutation that changes a once prop, request it (`only: ['categories']`) or rely on its state key.

## Controller Usage

- Paginate: `ItemIndexResponse::collect($paginator, PaginatedDataCollection::class)`; it yields `data`, `links`, `meta`.
- Eager-load relationships used by lazy properties (`->with([...])`) to avoid N+1.
- Flash: `Inertia::flash('key', $value)` (read from `page.flash`; not kept in history). Don't add flash messages to shared session props.

See: https://spatie.be/docs/laravel-data/v4/as-a-resource/from-data-to-resource
