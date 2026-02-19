## API DTOs (REST/JSON APIs)

- **Response DTO Location (MUST use):** `app/Data/Api/Responses`
- **Request DTO Location (MUST use):** `app/Data/Requests/Api`
- **Optimized for:** REST APIs and Scramble documentation generation
- **Lazy type:** `Lazy::create()` ONLY (NEVER use `Lazy::closure()` or `Lazy::inertia()`)
- **Attributes:** `#[AutoLazy]` is acceptable (NEVER use `#[AutoClosureLazy]` or `#[AutoInertiaLazy]`)
- **Documentation:** https://scramble.dedoc.co/packages/laravel-data

## Key Differences from Inertia DTOs

- **Location:** MUST be `app/Data/Api/Responses` (NEVER `app/Data/Responses`)
- **Lazy loading:** ALWAYS use `Lazy::create()` instead of `Lazy::closure()` or `Lazy::inertia()`
- **Scramble integration:** ALWAYS include `allowedRequestIncludes()` method
- **QueryBuilder:** MUST support `allowedIncludes()` and `allowedFields()` filtering

## Basic Pattern

<code-snippet name="API Response DTO" lang="php">
namespace App\Data\Api\Responses;

use App\Models\CaseNote;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class CaseNoteResponse extends Data
{
    public function __construct(
        public int $id,
        public ?string $text,
        public ?int $userId,
        /** @var Lazy|UserResponse */
        public Lazy|UserResponse $user,
        public ?Carbon $createdAt,
    ) {}

    public static function fromModel(CaseNote $note): self
    {
        return new self(
            id: $note->id,
            text: $note->text,
            userId: $note->user_id,
            user: Lazy::create(fn () => UserResponse::from($note->user)),
            createdAt: $note->created_at,
        );
    }

    // Required for Scramble documentation
    public static function allowedRequestIncludes(): ?array
    {
        return ['user'];
    }
}
</code-snippet>

## Controller with QueryBuilder

<code-snippet name="API Controller" lang="php">
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class CaseNotesApiController extends Controller
{
    public function index(int $id, IndexCaseNotesRequest $request): PaginatedDataCollection
    {
        $notes = QueryBuilder::for(CaseNote::class)
            ->where('case_id', $id)
            ->allowedSorts(['created_at', 'id'])
            ->allowedFilters([
                AllowedFilter::partial('text'),
                AllowedFilter::exact('user_id'),
            ])
            ->allowedFields(['id', 'text', 'user_id', 'created_at'])
            ->allowedIncludes(['user']) // Enables ?include=user
            ->paginate($request->perPage ?? 15);

        return CaseNoteResponse::collect($notes, PaginatedDataCollection::class);
    }
}
</code-snippet>

**Note:** Use Request DTOs to validate query parameters (page, perPage, sort, include, fields) - prevents QueryBuilder exceptions and provides proper validation errors.

API endpoint now supports:
- `?include=user` - Include user relationship
- `?filter[text]=keyword` - Filter by text
- `?sort=-created_at` - Sort by creation date
- `?fields[case_notes]=id,text` - Select specific fields

## Quick Reference

| Feature | Usage |
|---------|-------|
| **Response DTO Location (MUST)** | `app/Data/Api/Responses` |
| **Request DTO Location (MUST)** | `app/Data/Requests/Api` |
| **Lazy type** | ALWAYS `Lazy::create()` ONLY |
| **REQUIRED method** | ALWAYS include `allowedRequestIncludes()` for Scramble |
| **Controller** | Use Request DTOs, not generic `Request` |
| **Type order** | ALWAYS `Lazy\|Type` / NEVER `Type\|Lazy` |

