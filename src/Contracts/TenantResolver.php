<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Contracts;

/**
 * Resolves the tenant identifier for the current MCP request.
 *
 * Implement and bind your own when the default (reading the configured
 * column off the authenticated user) does not fit your tenancy model.
 */
interface TenantResolver
{
    /**
     * The current tenant identifier, or null when none can be resolved.
     */
    public function resolve(): mixed;
}
