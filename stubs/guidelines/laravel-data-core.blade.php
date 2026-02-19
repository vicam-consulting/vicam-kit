## Data Transfer Objects (DTOs)

- **Library:** ALWAYS use `spatie/laravel-data` for all DTOs.
- **Locations (MUST follow):**
  - Input → `app/Data/Requests`
  - Web/Inertia Output → `app/Data/Responses`
  - API Output → `app/Data/Api/Responses`
- **Construction:** ALWAYS use promoted, explicitly typed properties.
- **Documentation:** https://spatie.be/docs/laravel-data/v4/introduction

## Type Union Ordering

**CRITICAL:** When using union types with `Lazy` or `Optional`, **ALWAYS place `Lazy` or `Optional` first**. NEVER reverse the order.

CORRECT: `public Lazy|UserResponse $user`  
WRONG: `public UserResponse|Lazy $user`

CORRECT: `/** @var Lazy|DataCollection<int, CaseNoteResponse> */`  
WRONG: `/** @var DataCollection<int, CaseNoteResponse>|Lazy */`

## Custom Transformations (Casts & Transformers)

- **Casts (Input):** Convert input data into DTO properties (e.g., string → Carbon). Use `#[WithCast]` or `#[WithCastable]`.
- **Transformers (Output):** Convert DTO properties to output/JSON (e.g., Carbon → string). Use `#[WithTransformer]`.
- **When to use:** ONLY create custom casts/transformers when built-in laravel-data functionality doesn't meet your needs.

See: [Creating a cast](https://spatie.be/docs/laravel-data/v4/advanced-usage/creating-a-cast) | [Creating a transformer](https://spatie.be/docs/laravel-data/v4/advanced-usage/creating-a-transformer)

## Request DTOs (Validation)

ALWAYS replace FormRequest classes with laravel-data **Request DTOs**. Inject directly into controllers for auto-validation.

<code-snippet name="Input DTO with validation" lang="php">
class CreateUserRequest extends Data
{
    public function __construct(
        public string $email,
        public ?string $role,
        /** @var array<int> */ 
        public array $funeralHomeIds,
    ) {}
}
</code-snippet>

**Validation via:**
- Type hints (auto rule inferring)
- Validation attributes (`#[Max(20)]`, `#[Email]`)
- Manual rules (when needed)

See: [Request to data object](https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/request-to-data-object) | [Validation](https://spatie.be/docs/laravel-data/v4/validation/introduction)

## Property Naming

- Request DTOs MUST input camelCase keys (auto-configured in `config/data.php`)
- Response DTOs MUST output camelCase keys (auto-configured in `config/data.php`)

## TypeScript Generation

After changing DTOs, ALWAYS regenerate types: `php artisan typescript:transform`

ALWAYS import generated types from `resources/js/types/generated.ts` on the frontend. NEVER create custom types when a generated DTO exists.

See: [Transforming to TypeScript](https://spatie.be/docs/laravel-data/v4/advanced-usage/typescript)

## Key Docs

- [Introduction](https://spatie.be/docs/laravel-data/v4/introduction)
- [Creating a data object](https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/creating-a-data-object)
- [Nesting](https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/nesting)
- [Collections](https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/collections)
- [Validation](https://spatie.be/docs/laravel-data/v4/validation/introduction)

