# Subdomain-Based Multitenancy

- Use `spatie/laravel-multitenancy` v4 only when subdomain tenancy was explicitly selected.
- Resolve a tenant from the request host in a project-owned `TenantFinder`; keep host parsing out of controllers.
- Treat tenant discovery separately from data isolation. The application must document whether it uses a shared database, tenant database, or another boundary before adding tenant models or switch tasks.
- Reject unknown tenant hosts. Never fall back to a default tenant for an unrecognized production hostname.
- Generate URLs and signed URLs with the active tenant host, and cover host switching in feature tests.
- Queue payloads must carry enough tenant identity for the package to restore context safely.
