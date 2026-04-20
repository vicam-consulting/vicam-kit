## API DTOs (REST/JSON APIs)

- **Response DTO location (MUST):** `app/Data/Api/Responses`
- **Request DTO location (MUST):** `app/Data/Requests/Api`
- **Optimized for:** REST APIs + Scramble docs generation
- **Lazy type:** API DTO lazy properties MUST use `Lazy::create()` ONLY — NEVER `Lazy::closure()` / `Lazy::inertia()`.
- **Attributes:** `#[AutoLazy]` is acceptable; NEVER `#[AutoClosureLazy]` / `#[AutoInertiaLazy]`.
- **Docs:** https://scramble.dedoc.co/packages/laravel-data — https://scramble.dedoc.co/packages/laravel-query-builder

## Endpoint Shape

- **Read endpoints (`GET`) with public query params MUST use `spatie/laravel-query-builder`.**
- Mutation endpoints (`POST`/`PUT`/`PATCH`/`DELETE`) use API Request DTOs + explicit API Response DTOs — don't force QueryBuilder onto mutations.
- Controllers stay thin: authorize → call a small query class or action → return an API DTO / `JsonResponse`.
- Mutation controllers may adapt API DTOs into existing internal request DTOs before calling actions; keep that mapping thin and local.

## Read Endpoint Rules

- Validate public query params (`include`, `filter[...]`, `sort`, `page`, `perPage`) in API Request DTOs **before** QueryBuilder runs.
- Put QueryBuilder configuration in small query classes under `app/Queries/Api/...`, not controllers.
- Keep allowed values in the query class; reuse from the Request DTO via static helpers (`allowedIncludeNames()`, `allowedSortNames()`) so validation and QueryBuilder stay aligned.
- Use `QueryBuilder::for(...)` with explicit `allowedFilters()`, `allowedSorts()`, `allowedIncludes()`.
- Prefer **exact filters** for IDs, enums, booleans, status, foreign keys. Partial filters only for intentional text search.
- Include definitions should **not** expose `count` / `exists` variants unless that's part of the public contract.

## Query Request DTO Rules

- Extend a shared base (e.g. `ApiQueryRequest`) for parsing helpers — `delimitedValuesRule()` for validating comma-delimited strings, `parseDelimited()` for exposing parsed arrays via e.g. `requestedIncludes()`.
- Mark internal-only raw query fields with `#[Hidden]` so they don't appear in generated docs.
- Apply validation attributes like `#[Min(1)]` / `#[Max(100)]` on `page` / `perPage`.
- Keep index and show query DTOs small and endpoint-specific — do NOT create one giant reusable query request.

## Response DTO Rules

- Top-level **read** response DTOs that support request-driven includes should expose an `allowedRequestIncludes()` method listing **public read-time includes only**.
- If a mutation response shape differs materially from a read response, create a **dedicated mutation response DTO**.
- Keep API responses stable and safe: omit internal-only fields; mask secrets instead of exposing them raw.

## Naming

- Request bodies: camelCase keys (Laravel Data Request DTOs).
- Response DTOs: camelCase output keys.
- QueryBuilder params: QueryBuilder conventions — `filter[...]`, `sort`, `include`.

## Controller Patterns

- **Index:** inject `IndexXxxApiRequest` + `XxxIndexQuery`, authorize via `Gate::authorize(...)`, return `XxxApiResponse::collect($query->paginate($request->perPage), PaginatedDataCollection::class)->include(...$request->requestedIncludes())`.
- **Mutation:** inject model (route binding) + API Request DTO + action, authorize, call action, `$model->refresh()`, return `response()->json(XxxApiResponse::fromModel($model))`.

<code-snippet name="Query class shape" lang="php">
class ItemsIndexQuery
{
    public static function allowedIncludeNames(): array { return ['author', 'tags']; }
    public static function allowedSortNames(): array { return ['createdAt', 'id']; }

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return QueryBuilder::for(Item::class)
            ->allowedIncludes(
                AllowedInclude::relationship('author'),
                AllowedInclude::relationship('tags'),
            )
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
            )
            ->allowedSorts(
                AllowedSort::field('createdAt', 'created_at'),
                AllowedSort::field('id'),
            )
            ->defaultSort(AllowedSort::field('createdAt', 'created_at')->defaultDirection(SortDirection::Descending))
            ->paginate($perPage)
            ->withQueryString();
    }
}
</code-snippet>
