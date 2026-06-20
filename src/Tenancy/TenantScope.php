<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tenancy;

use Blaze\ModelMcp\Contracts\TenantResolver;
use Blaze\ModelMcp\Registry\ModelDescriptor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Optional explicit tenant scoping, applied to the QUERY before any policy
 * check so cross-tenant rows can never surface — even if a policy is missing or
 * permissive (defense in depth).
 *
 * When disabled (the default) this is a no-op and the package relies entirely
 * on the host app's existing Eloquent global scopes, which are never stripped.
 */
final class TenantScope
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly Config $config,
    ) {
        //
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('model-mcp.tenancy.enabled', false);
    }

    /**
     * Constrain a query to the current tenant. Fails closed when the tenant
     * cannot be resolved (configurable).
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     *
     * @throws AuthorizationException
     */
    public function apply(Builder $query, ModelDescriptor $descriptor): Builder
    {
        if (! $this->enabled()) {
            return $query;
        }

        $column = $this->column($descriptor);
        $tenantId = $this->tenantId();

        if ($tenantId === null) {
            // tenantId() only returns null when fail_closed is off. Never fall back
            // to an unscoped query — return an empty set instead of every tenant.
            return $query->whereRaw('0 = 1');
        }

        return $query->where($query->getModel()->qualifyColumn($column), $tenantId);
    }

    /**
     * Stamp the current tenant onto a model about to be created.
     *
     * @throws AuthorizationException
     */
    public function stamp(Model $model, ModelDescriptor $descriptor): void
    {
        if (! $this->enabled()) {
            return;
        }

        $tenantId = $this->tenantId();

        if ($tenantId !== null) {
            $model->setAttribute($this->column($descriptor), $tenantId);
        }
    }

    private function column(ModelDescriptor $descriptor): string
    {
        return $descriptor->tenantColumn
            ?? (string) $this->config->get('model-mcp.tenancy.column', 'tenant_id');
    }

    /**
     * @throws AuthorizationException
     */
    private function tenantId(): mixed
    {
        $tenantId = $this->resolver->resolve();

        if ($tenantId === null && (bool) $this->config->get('model-mcp.tenancy.fail_closed', true)) {
            throw new AuthorizationException('Unable to resolve the current tenant for this request.');
        }

        return $tenantId;
    }
}
