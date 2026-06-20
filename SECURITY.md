# Security Policy

`laravel-model-mcp` exposes your database to AI agents. We take that
responsibility seriously, and so should you.

## The security model

This package is **safe by default**:

1. **Nothing is exposed implicitly.** Only models you list in
   `config/model-mcp.php` (or explicitly tag with `#[McpModel]`) become tools.
2. **Every tool enforces a Laravel Policy.** Each operation runs the matching
   ability (`viewAny`/`view`/`create`/`update`/`delete`) for the authenticated
   MCP user before any data is read or written.
3. **Fail-closed.** A model with no registered policy is denied entirely
   (`authorization.deny_without_policy`). Unauthenticated requests are denied
   (`authorization.require_authentication`).
4. **Tenant scope is applied to the query before the policy check**, so a missing
   or permissive policy still cannot leak another tenant's rows. When tenancy is
   enabled and no tenant resolves, requests fail closed.
5. **Writes are limited to `$fillable`**, and `fields.always_hidden` is a hard
   block on top of the model's `$hidden`.

## Known trade-offs

- **Collection reads authorize at `viewAny`, not per row.** `list` and `search`
  check the `viewAny` ability, then return every row the query returns. They do
  **not** run `view()` on each row — per-row visibility must be enforced with an
  Eloquent **global scope** (or your tenant scoping), not just a `view()` policy
  method. This mirrors how Laravel itself treats collection authorization, and it
  is why tenant scoping is applied at the query level. `get`/`update`/`delete`
  authorize the specific record.
- **`delete` respects `SoftDeletes`.** If the model uses the trait, records are
  soft-deleted (and excluded from `list`/`search`); otherwise the delete is
  permanent. There is no restore tool yet.

You are still responsible for:

- Writing correct policies for every exposed model.
- Authenticating the MCP transport (e.g. `Mcp::web(...)->middleware('auth:sanctum')`
  or `Mcp::oauthRoutes()`).
- Reviewing the output of `php artisan model-mcp:list` before shipping.

## Reporting a vulnerability

If you discover a security vulnerability — especially anything that could let an
MCP client exceed a user's policy or read across tenants — please **do not open a
public issue**.

Email **security@blaze.uz** with the details and a reproduction. We will
acknowledge within 72 hours and keep you informed through to a fix. Please give
us a reasonable window to release a patch before any public disclosure.
