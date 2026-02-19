# Architecture Guidelines

These guidelines describe how this project uses **Spatie Laravel Data**, **Action classes**, **Services**, **Jobs**, and **Events/Listeners**.

## High-Level Flow
Controller → **Request DTO** (laravel-data, validated) → **Action** → (Response DTO | model) → Controller → **Inertia render/redirect**

## Component Boundaries

Use the right building block for the right kind of work:

- **Actions (`app/Actions`)**  
  Simple, synchronous business logic.  
  Example: CRUD, calculations, data transformations (<1 second).
- **Jobs (queued)**  
  Heavy, slow, or retryable work.  
  Example: file processing, bulk operations, report generation.
- **Events & Listeners**  
  Cross-cutting side effects, decoupled workflows.  
  Example: email notifications, audit logs, webhooks.
- **Services**  
  Wrappers for external APIs, integrations, or cross-cutting infrastructure (e.g. S3, Stripe, HTTP clients).
**Rule of thumb:** Controllers stay thin and delegate — call an Action, dispatch a Job, or fire an Event.

## Controllers
- Thin orchestration only:  
  `$response = $action->execute($requestDto); return Inertia::render(...)`
- Never contain persistence or business logic.

### Inertia Responses
- All data returned to Inertia must be wrapped in **Response DTOs** from `app/Data/Responses`.
- Controllers must not pass raw arrays/models/loose objects/etc. to Inertia. Only use Response DTOs.
- Construct Response DTOs via `fromModel()` (or `from()`), supporting nesting and collections as needed or via casts/custom casts for properties.

## Request DTOs
- Use **spatie/laravel-data** Request DTOs for input payloads and validation (no FormRequest classes).
- Inject Request DTOs directly into controllers; they are auto-filled and validated from the current request. See docs: https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/request-to-data-object
- Name input DTOs with the `Request` suffix (e.g., `CreateUserRequest`). Name output DTOs with the `Response` suffix (e.g., `UserResponse`).
- Define validation via type declarations, auto rule inferring, and optional validation attributes. For advanced cases, add manual rules or validator hooks.

## DTOs (Spatie Laravel Data)
- **Request DTOs** (input) are passed to Actions.  
- **Response DTOs** are returned from Actions and passed to Inertia.  
- **Response DTOs** must be used when sending data to Inertia.
- **Request DTOs** own validation through laravel-data (auto-inferring, attributes, manual rules as needed); DTOs contain no business logic or I/O.