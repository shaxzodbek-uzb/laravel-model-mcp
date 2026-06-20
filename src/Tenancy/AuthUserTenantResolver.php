<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tenancy;

use Blaze\ModelMcp\Contracts\TenantResolver;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Default resolver: reads the configured tenant column off the authenticated
 * MCP user (e.g. `$user->tenant_id`).
 */
final class AuthUserTenantResolver implements TenantResolver
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Config $config,
    ) {
        //
    }

    public function resolve(): mixed
    {
        $user = $this->auth->guard()->user();

        if ($user === null) {
            return null;
        }

        $column = (string) $this->config->get('model-mcp.tenancy.column', 'tenant_id');

        if (method_exists($user, 'getAttribute')) {
            return $user->getAttribute($column);
        }

        return $user->{$column} ?? null;
    }
}
