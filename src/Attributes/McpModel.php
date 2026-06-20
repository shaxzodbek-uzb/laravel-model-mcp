<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Attributes;

use Attribute;

/**
 * Opt an Eloquent model in to MCP exposure.
 *
 * Only honored when `model-mcp.discovery.enabled` is true. The explicit
 * `model-mcp.models` allow-list is always the safer default.
 *
 *   #[McpModel(operations: ['list', 'view'], tenantColumn: 'team_id')]
 *   class Post extends Model { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class McpModel
{
    /**
     * @param  list<string>|null  $operations  Operations to expose (null = package default).
     * @param  string|null  $tenantColumn  Column used for tenant scoping on this model.
     * @param  class-string|null  $policy  Policy class to enforce (defaults to Laravel's resolution).
     * @param  string|null  $name  Tool name stem (defaults to the snake_case model name).
     */
    public function __construct(
        public ?array $operations = null,
        public ?string $tenantColumn = null,
        public ?string $policy = null,
        public ?string $name = null,
    ) {
        //
    }
}
