=== multitenancy/core rules ===

## Laravel Multitenancy (Spatie)

This project uses Spatie Laravel Multitenancy v4 with a single database configuration. Documentation: https://spatie.be/docs/laravel-multitenancy/v4/introduction

## Core Configuration

- **Single Database Setup**: All tenants share the same database, differentiated by `tenant_id` columns
- **Domain/Subdomain Tenant Resolution**: Uses `DomainOrSubdomainTenantFinder` to determine current tenant
- **Cache Prefixing**: Enabled via `PrefixCacheTask` to isolate tenant data in cache

## Tenant-Aware Models

### BelongsToTenant Trait
- **Use for all tenant-scoped models**: Any model that should be filtered by tenant must use the `BelongsToTenant` trait
- **Automatic tenant scoping**: Models are automatically filtered to the current tenant's data
- **Auto-assignment**: New models automatically get the current tenant's ID when created

<code-snippet name="Model with Tenant Awareness" lang="php">
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class YourModel extends Model
{
    use BelongsToTenant;

    // Model automatically scoped to current tenant
    // tenant_id automatically set on creation
}
</code-snippet>

### Available Scopes
- **`withoutTenantScope()`**: Remove tenant filtering for admin operations or cross-tenant queries
- **`forTenant(Tenant $tenant)`**: Query specific tenant's data

<code-snippet name="Using Tenant Scopes" lang="php">
// Get all records across tenants (admin operation)
$allUsers = User::withoutTenantScope()->get();

// Query specific tenant's data
$tenantUsers = User::forTenant($specificTenant)->get();
</code-snippet>

## Database Schema
- **Add `tenant_id` column**: All tenant-aware tables must include a `tenant_id` foreign key
- **Foreign key constraints**: Link to the landlord `tenants` table

## Current Tenant Access
- **Access current tenant**: Use `Tenant::current()` to get the active tenant
- **Tenant relationship**: Access via `$model->tenant()` relationship method
