<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Server;

use Blaze\ModelMcp\Tools\ToolFactory;
use Laravel\Mcp\Server;

/**
 * A ready-to-register MCP server that auto-exposes every model configured in
 * `model-mcp.models` as policy-enforced CRUD tools.
 *
 * Register it in routes/ai.php:
 *
 *   use Blaze\ModelMcp\Server\ModelMcpServer;
 *   use Laravel\Mcp\Facades\Mcp;
 *
 *   Mcp::web('/mcp/models', ModelMcpServer::class)->middleware(['auth:sanctum']);
 *   // or, for local/stdio:
 *   Mcp::local('models', ModelMcpServer::class);
 *
 * Subclass it to set your own name/instructions or to add hand-written tools
 * alongside the generated ones.
 */
class ModelMcpServer extends Server
{
    protected string $name = 'Model MCP Server';

    protected string $version = '0.1.0';

    protected string $instructions = <<<'MARKDOWN'
        This server exposes your application's Eloquent models as CRUD tools.
        Every call is checked against the authenticated user's Laravel policies
        and scoped to their tenant — you can only do what your account permits.
        Use the list_* and search_* tools to discover records (they are
        paginated), get_* to read one, and create_*/update_*/delete_* to modify.
        MARKDOWN;

    /**
     * Populate the generated tools. boot() runs before the server builds its
     * context, on both the web (HTTP) and local (stdio) transports.
     */
    protected function boot(): void
    {
        $this->tools = [
            ...app(ToolFactory::class)->make(),
            ...$this->tools,
        ];
    }
}
