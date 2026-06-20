<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Authorization;

use Blaze\ModelMcp\Registry\ModelDescriptor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Gate as ConcreteGate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;

/**
 * Enforces the host application's Laravel Policy for every generated tool call.
 *
 * This is the package's headline guarantee: an MCP client can only do through a
 * tool what the authenticated user could do through a Policy. The default is
 * fail-closed — a model with no Policy is denied unless you opt out.
 */
final class PolicyGuard
{
    public function __construct(
        private readonly Gate $gate,
        private readonly Config $config,
    ) {
        //
    }

    /**
     * Authorize an operation, throwing on denial.
     *
     * @param  Model|class-string<Model>  $target  Instance for view/update/delete; class-string for viewAny/create.
     *
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function authorize(?Authenticatable $user, ModelDescriptor $descriptor, string $ability, Model|string $target): void
    {
        if (! $this->enabled()) {
            return;
        }

        if ($user === null && $this->requiresAuthentication()) {
            throw new AuthenticationException('This MCP tool requires an authenticated user.');
        }

        if ($descriptor->policyClass !== null) {
            $this->gate->policy($descriptor->modelClass, $descriptor->policyClass);
        }

        if (! $this->hasPolicy($descriptor->modelClass)) {
            if ($this->denyWithoutPolicy()) {
                throw new AuthorizationException(
                    "No authorization policy is registered for [{$descriptor->modelClass}].",
                );
            }

            return;
        }

        $gate = $user !== null ? $this->gate->forUser($user) : $this->gate;

        // Throws AuthorizationException when the ability is not granted.
        $gate->authorize($ability, $target);
    }

    /**
     * Resolve the policy ability for an operation, honoring config overrides.
     */
    public function abilityFor(string $operation): string
    {
        /** @var array<string, string> $map */
        $map = $this->config->get('model-mcp.authorization.abilities', []);

        return $map[$operation] ?? match ($operation) {
            'list', 'search' => 'viewAny',
            'view' => 'view',
            'create' => 'create',
            'update' => 'update',
            'delete' => 'delete',
            default => $operation,
        };
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function hasPolicy(string $modelClass): bool
    {
        // getPolicyFor() lives on the concrete Gate, not the contract.
        if ($this->gate instanceof ConcreteGate) {
            return $this->gate->getPolicyFor($modelClass) !== null;
        }

        // Unknown Gate implementation: assume a policy exists and let authorize()
        // make the real decision (safer than silently allowing).
        return true;
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('model-mcp.authorization.enabled', true);
    }

    private function requiresAuthentication(): bool
    {
        return (bool) $this->config->get('model-mcp.authorization.require_authentication', true);
    }

    private function denyWithoutPolicy(): bool
    {
        return (bool) $this->config->get('model-mcp.authorization.deny_without_policy', true);
    }
}
