<?php

declare(strict_types=1);

use Blaze\ModelMcp\Audit\LogAuditor;
use Blaze\ModelMcp\Tenancy\AuthUserTenantResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Exposed models
    |--------------------------------------------------------------------------
    |
    | An explicit allow-list is the safe default: nothing is ever exposed over
    | MCP unless you list it here. Use a bare class-string to accept the
    | defaults, or `Class::class => [...]` to override options per model.
    |
    |   \App\Models\Post::class,
    |   \App\Models\Comment::class => [
    |       'operations'    => ['list', 'view', 'create', 'update'],
    |       'tenant_column' => 'team_id',
    |       'policy'        => \App\Policies\CommentPolicy::class,
    |       'name'          => 'comment',     // tool name stem (defaults to the model)
    |   ],
    |
    */
    'models' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute discovery
    |--------------------------------------------------------------------------
    |
    | Optionally discover models tagged with #[Blaze\ModelMcp\Attributes\McpModel].
    | Disabled by default so a model is never exposed by accident — opt in only
    | if you are comfortable with attribute-driven exposure.
    |
    */
    'discovery' => [
        'enabled' => false,
        'paths' => [app_path('Models')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default operations
    |--------------------------------------------------------------------------
    |
    | The operations exposed for an opted-in model when it does not override
    | them. Each maps to one generated MCP tool. Supported:
    | list, view, create, update, delete, search.
    |
    */
    'operations' => ['list', 'view', 'create', 'update', 'delete', 'search'],

    /*
    |--------------------------------------------------------------------------
    | Read-only kill-switch
    |--------------------------------------------------------------------------
    |
    | When true, no write tools are ever generated for ANY model — only list,
    | view and search survive, regardless of per-model operations. A single,
    | auditable switch for "let agents read, never mutate".
    |
    */
    'read_only' => false,

    /*
    |--------------------------------------------------------------------------
    | Tool naming
    |--------------------------------------------------------------------------
    |
    | MCP clients expect snake_case tool names. `scheme` controls word order
    | (verb_model => "list_posts", model_verb => "posts_list").
    |
    */
    'naming' => [
        'scheme' => 'verb_model',
        'pluralize' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | The headline feature: every generated tool runs the matching Laravel
    | Policy ability for the authenticated MCP user BEFORE touching data.
    |
    | - deny_without_policy: when a model has no registered Policy, refuse every
    |   operation. This is the safe default; flip it only if you really intend
    |   to expose un-policied models.
    | - require_authentication: refuse tools when the MCP request has no user.
    | - abilities: operation => policy ability override map.
    |
    */
    'authorization' => [
        'enabled' => true,
        'deny_without_policy' => true,
        'require_authentication' => true,
        'abilities' => [
            'list' => 'viewAny',
            'search' => 'viewAny',
            'view' => 'view',
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | By default this package adds NOTHING and relies entirely on your existing
    | Eloquent global scopes (BelongsToTenant, #[ScopedBy], stancl/tenancy,
    | spatie/laravel-multitenancy) — queries run through Model::query() so those
    | scopes apply transparently and are never stripped.
    |
    | Enable this only for setups where a query scope alone won't filter (e.g.
    | you want this package to add an explicit `where(column, tenant_id)`).
    | When enabled and no tenant can be resolved, requests fail closed.
    |
    */
    'tenancy' => [
        'enabled' => false,
        'column' => 'tenant_id',
        'resolver' => AuthUserTenantResolver::class,
        'fail_closed' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Field exposure
    |--------------------------------------------------------------------------
    |
    | Reads honor the model's $hidden; `always_hidden` is an extra hard block on
    | top of it. Writes are always limited to the model's $fillable.
    |
    */
    'fields' => [
        'respect_hidden' => true,
        'always_hidden' => ['password', 'remember_token'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Auto-exposed list/search tools MUST be bounded or they will blow an
    | agent's context window. These caps are enforced, not advisory.
    |
    */
    'pagination' => [
        'default_per_page' => 25,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response shape
    |--------------------------------------------------------------------------
    |
    | 'concise' trims list rows to the key plus a label column to save tokens;
    | 'detailed' returns every visible attribute. Per-call `response_format`
    | argument overrides this.
    |
    */
    'response' => [
        'default_format' => 'concise',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    |
    | Every tool call — allowed or denied — can be recorded with the acting
    | user, model, operation and outcome. Swap `logger` for your own
    | Blaze\ModelMcp\Contracts\ToolAuditor (e.g. write to a database table).
    |
    */
    'audit' => [
        'enabled' => true,
        'logger' => LogAuditor::class,
        'channel' => null,
        'log_allowed' => true,
        'log_denied' => true,
    ],

];
