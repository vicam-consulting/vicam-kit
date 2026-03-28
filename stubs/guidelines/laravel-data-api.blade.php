## API DTOs (REST/JSON APIs)

- **Response DTO Location (MUST use):** `app/Data/Api/Responses`
- **Request DTO Location (MUST use):** `app/Data/Requests/Api`
- **Optimized for:** REST APIs and Scramble documentation generation
- **Lazy type:** API DTO lazy properties MUST use `Lazy::create()` ONLY (NEVER use `Lazy::closure()` or `Lazy::inertia()`)
- **Attributes:** `#[AutoLazy]` is acceptable (NEVER use `#[AutoClosureLazy]` or `#[AutoInertiaLazy]`)
- **Docs:** https://scramble.dedoc.co/packages/laravel-data and https://scramble.dedoc.co/packages/laravel-query-builder

## Endpoint Shape

- **Read endpoints (`GET`)** with public query params MUST use `spatie/laravel-query-builder`.
- **Mutation endpoints (`POST`, `PUT`, `PATCH`, `DELETE`)** do not need QueryBuilder; use API Request DTOs + explicit API Response DTOs.
- Controllers stay thin: authorize, call a small query class or action, return an API DTO / JSON response.
- Mutation controllers may adapt API DTOs into existing internal request DTOs before calling actions; keep that mapping thin and local to the controller.
- Do not force QueryBuilder patterns onto mutations.

## Read Endpoint Rules

- Validate public query params in API Request DTOs **before** QueryBuilder runs.
- Today that usually means `include`, `filter[...]`, `sort`, `page`, and `perPage`.
- Put QueryBuilder configuration in small query classes in `app/Queries/Api/...`, not in controllers.
- Keep the allowed query values in the query class and reuse them from the Request DTO. Prefer static helpers like `allowedIncludeNames()` / `allowedSortNames()` so validation and QueryBuilder stay aligned.
- Use `QueryBuilder::for(...)` plus explicit `allowedFilters()`, `allowedSorts()`, and `allowedIncludes()`.
- Prefer **exact filters** for IDs, enums, booleans, status fields, and foreign keys.
- Use **partial filters** only for intentional text search.
- Prefer explicit include definitions that do **not** expose count / exists variants unless that is part of the public API contract.

## Query Request DTO Rules

- Shared parsing / validation helpers belong in a base API query DTO such as `ApiQueryRequest`.
- For comma-delimited params like `include` and `sort`, validate the raw string and expose a parsed helper like `requestedIncludes()`.
- Keep index and show query DTOs small and endpoint-specific. Do not create one giant reusable query request with optional everything.

## DTO Rules

- Top-level **read** response DTOs that support request-driven includes should expose `allowedRequestIncludes()`.
- `allowedRequestIncludes()` should describe **public read-time includes only**.
- If a mutation response shape differs materially from a read response, create a **dedicated mutation response DTO**.
- Keep API responses intentionally safe and stable. Omit internal-only fields, and mask secrets or sensitive values instead of exposing them raw.

## Naming Rules

- **Request bodies** use camelCase keys via Laravel Data Request DTOs.
- **Response DTOs** output camelCase keys.
- **QueryBuilder query params** follow QueryBuilder conventions: `filter[...]`, `sort`, `include`.

## Basic Patterns

<code-snippet name="API Query Request" lang="php">
class IndexItemsApiRequest extends ApiQueryRequest
{
    /** @param  array<string, mixed>|null  $filter */
    public function __construct(
        #[Hidden]
        public ?string $include = null,
        #[Hidden]
        public ?array $filter = null,
        #[Hidden]
        public ?string $sort = null,
        #[Min(1)]
        public int $page = 1,
        #[Min(1), Max(100)]
        public int $perPage = 15,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'include' => ['nullable', 'string', static::delimitedValuesRule(ItemsIndexQuery::allowedIncludeNames())],
            'sort' => ['nullable', 'string', static::delimitedValuesRule(ItemsIndexQuery::allowedSortNames(), true)],
            'filter' => ['nullable', 'array:status,type'],
            'filter.status' => ['nullable', Rule::in(array_column(ItemStatus::cases(), 'value'))],
            'filter.type' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, string> */
    public function requestedIncludes(): array
    {
        return static::parseDelimited($this->include);
    }
}
</code-snippet>

<code-snippet name="API Read Query + Controller" lang="php">
class ItemsIndexQuery
{
    /** @return array<int, string> */
    public static function allowedIncludeNames(): array
    {
        return ['author', 'tags'];
    }

    /** @return array<int, string> */
    public static function allowedSortNames(): array
    {
        return ['createdAt', 'id'];
    }

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

class ItemApiController extends Controller
{
    public function index(IndexItemsApiRequest $request, ItemsIndexQuery $query): PaginatedDataCollection
    {
        Gate::authorize('viewAny', Item::class);

        return ItemApiResponse::collect(
            $query->paginate($request->perPage),
            PaginatedDataCollection::class,
        )->include(...$request->requestedIncludes());
    }
}
</code-snippet>

<code-snippet name="API Mutation Controller" lang="php">
class ItemApiController extends Controller
{
    public function update(
        Item $item,
        UpdateItemApiRequest $request,
        UpdateItemAction $action,
    ): JsonResponse {
        Gate::authorize('update', $item);

        $action->execute($item, $request);

        $item->refresh();

        return response()->json(ItemApiResponse::fromModel($item));
    }
}
</code-snippet>
