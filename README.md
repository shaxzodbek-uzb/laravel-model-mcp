# Laravel Model MCP

<p>
    <a href="https://packagist.org/packages/shaxzodbek-uzb/laravel-model-mcp"><img src="https://img.shields.io/packagist/v/shaxzodbek-uzb/laravel-model-mcp.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://packagist.org/packages/shaxzodbek-uzb/laravel-model-mcp"><img src="https://img.shields.io/packagist/php-v/shaxzodbek-uzb/laravel-model-mcp?style=flat-square" alt="PHP Version"></a>
    <a href="LICENSE"><img src="https://img.shields.io/packagist/l/shaxzodbek-uzb/laravel-model-mcp.svg?style=flat-square" alt="License"></a>
</p>

**Expose your Eloquent models to AI agents as MCP tools — without handing them the keys to your database.**

`laravel-model-mcp` turns any Eloquent model into a full set of [Model Context
Protocol](https://modelcontextprotocol.io) tools (`list`, `view`, `create`,
`update`, `delete`, `search`) on top of the official
[`laravel/mcp`](https://github.com/laravel/mcp) package — and every single call
is checked against your **Laravel Policies**, scoped to the **current tenant**,
and **audited**. Safe by default, no boilerplate.

---

## The problem

The official `laravel/mcp` package is excellent, but it makes you hand-write one
tool class per operation, and authorization is a manual `if` inside each handler:

```php
class UpdatePostTool extends Tool
{
    public function handle(Request $request): Response
    {
        $post = Post::findOrFail($request->get('id'));

        // You have to remember this, on every tool, for every model.
        if (! $request->user()->can('update', $post)) {
            return Response::error('Forbidden.');
        }

        $post->update($request->validate([...]));

        return Response::json($post);
    }

    public function schema(JsonSchema $schema): array
    {
        // ...and hand-maintain a schema that mirrors your migration.
    }
}
```

Multiply that by 6 operations × every model you want to expose. Forget the
`can()` check on one of them, and an agent can now edit anything.

## The solution

List the models. Register one server. Done — policy-enforced CRUD for all of them:

```php
// config/model-mcp.php
'models' => [
    App\Models\Post::class,
    App\Models\Comment::class,
],
```

```php
// routes/ai.php
use Blaze\ModelMcp\Server\ModelMcpServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/models', ModelMcpServer::class)->middleware(['auth:sanctum']);
```

That's it. You now have `list_posts`, `get_post`, `create_post`, `update_post`,
`delete_post`, `search_posts`, `describe_post` (and the same for `comment`) — each one running
`PostPolicy@viewAny`, `@view`, `@create`, `@update`, `@delete` for the
authenticated user before it touches a row.

## Why this package

| | `laravel/mcp` alone | `laravel-model-mcp` |
|---|:---:|:---:|
| Eloquent → MCP CRUD tools | hand-written | **auto-generated** |
| Laravel Policy enforced per call | manual `can()` in each handler | **built in, fail-closed** |
| Multi-tenant row scoping | DIY | **built in** |
| Audit log of every tool call | DIY | **built in** |
| Token-safe pagination & field limits | DIY | **built in** |
| JSON Schema from casts/columns | hand-written | **generated** |

This package **builds on** `laravel/mcp` — it does not replace it. Transport,
OAuth, and the protocol stay with the official package; this layer adds the
opinionated, safe-by-default model exposure on top.

## Installation

```bash
composer require shaxzodbek-uzb/laravel-model-mcp
```

Requires PHP 8.2+ and Laravel 12.41+ / 13.x (it relies on `laravel/mcp`'s
JSON Schema builder). If you haven't set up `laravel/mcp` yet:

```bash
php artisan vendor:publish --tag=ai-routes   # creates routes/ai.php
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=model-mcp-config
```

## Quickstart

**1. Add a policy** for the model you want to expose (standard Laravel — nothing
special):

```php
class PostPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Post $post): bool { return true; }
    public function create(User $user): bool { return $user->can_write; }
    public function update(User $user, Post $post): bool { return $user->id === $post->user_id; }
    public function delete(User $user, Post $post): bool { return $user->id === $post->user_id; }
}
```

**2. Expose the model:**

```php
// config/model-mcp.php
'models' => [
    App\Models\Post::class,
],
```

**3. Register the server** in `routes/ai.php` behind your auth middleware:

```php
Mcp::web('/mcp/models', \Blaze\ModelMcp\Server\ModelMcpServer::class)
    ->middleware(['auth:sanctum']);
```

**4. See exactly what you exposed:**

```bash
php artisan model-mcp:list

#  Tool          Model               Operation
#  list_posts    App\Models\Post     list
#  get_post      App\Models\Post     view
#  create_post   App\Models\Post     create
#  update_post   App\Models\Post     update
#  delete_post   App\Models\Post     delete
#  search_posts  App\Models\Post     search
#  describe_post App\Models\Post     describe
```

Point any MCP client (Claude, your agent, `php artisan mcp:inspector`) at the
server and the tools are live — each one acting as the authenticated user.

## The security model

This is the whole point, so it's worth being explicit. By default:

1. **Nothing is exposed implicitly.** Only models in `model-mcp.models` (or tagged
   with `#[McpModel]`) become tools.
2. **Every operation enforces the matching policy ability** for the authenticated
   MCP user, *before* any read or write:

   | Operation | Policy ability |
   |---|---|
   | `list`, `search` | `viewAny` |
   | `view` | `view` |
   | `create` | `create` |
   | `update` | `update` |
   | `delete` | `delete` |

3. **Fail-closed.** No policy for the model → every operation is denied. No
   authenticated user → denied. (Both configurable, both default to safe.)
4. **Tenant scope is applied to the query *before* the policy runs**, so even a
   missing or over-permissive policy can't leak another tenant's rows. If tenancy
   is enabled and no tenant resolves, the request fails closed.
5. **Writes are limited to `$fillable`**; reads honor `$hidden`; and
   `fields.always_hidden` is a hard block on top (e.g. `password`).

Denials and errors come back as MCP `isError` results with safe messages — the
agent can recover, and your internals never leak. Every call (allowed, denied,
errored) is recorded by the [audit log](#audit-log).

> See [SECURITY.md](SECURITY.md) for the full model and how to report issues.

## Configuration

The published `config/model-mcp.php` is fully documented. The essentials:

```php
return [
    // Explicit allow-list. Bare class, or Class => [overrides].
    'models' => [
        App\Models\Post::class,
        App\Models\Invoice::class => [
            'operations'    => ['list', 'view'],          // read-only
            'tenant_column' => 'team_id',
            'policy'        => App\Policies\InvoicePolicy::class,
            'name'          => 'invoice',                 // tool name stem
        ],
    ],

    // Operations exposed by default for an opted-in model.
    'operations' => ['list', 'view', 'create', 'update', 'delete', 'search', 'describe'],

    'authorization' => [
        'enabled'                => true,
        'deny_without_policy'    => true,   // fail closed
        'require_authentication' => true,
    ],

    'tenancy' => [
        'enabled'  => false,                // rely on your global scopes by default
        'column'   => 'tenant_id',
        'resolver' => Blaze\ModelMcp\Tenancy\AuthUserTenantResolver::class,
        'fail_closed' => true,
    ],

    'pagination' => ['default_per_page' => 25, 'max_per_page' => 100],

    'audit' => ['enabled' => true, 'logger' => Blaze\ModelMcp\Audit\LogAuditor::class],
];
```

### Read-only or partial exposure

Expose only the operations you want, per model:

```php
'models' => [
    App\Models\AuditEntry::class => ['operations' => ['list', 'view', 'search']],
],
```

Or flip a single global kill-switch so **no** model can ever be mutated — only
`list` / `view` / `search` tools are generated, regardless of per-model settings:

```php
'read_only' => true,
```

## `describe` — so the agent stops guessing

Without it, the only way for an agent to learn a model's shape is to call `list`
and read whatever comes back. That needs a row to exist, needs the caller to be
allowed to see it, and *still* says nothing about which fields are writable,
which are required, or what a date column expects. Each unknown becomes a failed
`create` and a retry.

```jsonc
// describe_post
{
  "model": "Post",
  "key": { "name": "id", "type": "int" },
  "operations": ["list", "view", "create", "update", "delete", "search", "describe"],
  "fields": [
    { "name": "id",     "type": "integer", "writable": false, "primary_key": true, "nullable": false },
    { "name": "title",  "type": "string",  "writable": true,  "nullable": false, "required_on_create": true },
    { "name": "status", "type": "string",  "writable": true,  "nullable": true,  "cast": "App\\Enums\\PostStatus" }
  ],
  "notes": [
    "Rows are scoped by \"team_id\"; it is set automatically and cannot be supplied.",
    "Every operation is still checked against the policy and tenant scope — a field appearing here does not mean the current user may read or write it."
  ]
}
```

It is **metadata only**: it reads the model's schema and this package's config,
never a row. So it needs no tenant scope and cannot leak data.

It is still gated on `viewAny`, and it hides exactly what every other response
hides (`$hidden`, `fields.always_hidden`). Which models exist and what columns
they have is itself information — `describe` must not become the way to discover
a column the model deliberately hides. Switch it off per model like any other
operation.

## Multi-tenancy

If your app already scopes models with **global scopes** (a `BelongsToTenant`
trait, `#[ScopedBy]`, `stancl/tenancy`, `spatie/laravel-multitenancy`), you need
to do **nothing** — every query runs through `Model::query()`, so your scopes
apply transparently and are never stripped.

Turn on the package's own explicit scoping only when a global scope alone won't
filter (e.g. you want a hard `where(tenant_column, id)` regardless):

```php
'tenancy' => [
    'enabled' => true,
    'column'  => 'tenant_id',   // default; override per model via 'tenant_column'
],
```

The default `AuthUserTenantResolver` reads the column off the authenticated user
(`$user->tenant_id`). Provide your own by binding
`Blaze\ModelMcp\Contracts\TenantResolver`.

## Audit log

Every tool call is recorded with the acting user, the model, the operation, and
the outcome (`allowed` / `denied` / `error`). The default `LogAuditor` writes to
your log channel; swap in your own to persist to a table:

```php
use Blaze\ModelMcp\Contracts\ToolAuditor;
use Blaze\ModelMcp\Audit\ToolCallEvent;

class DatabaseAuditor implements ToolAuditor
{
    public function record(ToolCallEvent $event): void
    {
        McpAuditLog::create($event->toArray());
    }
}
```

```php
// config/model-mcp.php
'audit' => ['enabled' => true, 'logger' => App\Mcp\DatabaseAuditor::class],
```

## Attribute discovery (optional)

Prefer to opt in from the model itself? Enable discovery and tag your models —
it's off by default so nothing is ever exposed by accident:

```php
use Blaze\ModelMcp\Attributes\McpModel;

#[McpModel(operations: ['list', 'view'], tenantColumn: 'team_id')]
class Post extends Model { /* ... */ }
```

```php
'discovery' => ['enabled' => true, 'paths' => [app_path('Models')]],
```

## Extending

- **Custom tools alongside generated ones** — subclass `ModelMcpServer` and add
  your hand-written tools to the `$tools` array; the generated ones are merged in.
- **Custom policies** — point a model's `policy` option at any class, or rely on
  Laravel's normal policy resolution.
- **Custom auditor / tenant resolver** — implement the contract and bind it.

## Requirements

- PHP 8.2+
- Laravel 12.41+ or 13.x
- [`laravel/mcp`](https://github.com/laravel/mcp) ^0.8

## Testing

```bash
composer test       # Pest
composer analyse    # PHPStan / Larastan (level 6)
composer lint       # Pint
```

## Credits

Built by [Blaze](https://blaze.uz). Stands on the shoulders of the
[`laravel/mcp`](https://github.com/laravel/mcp) team.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
