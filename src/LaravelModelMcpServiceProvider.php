<?php

declare(strict_types=1);

namespace Blaze\ModelMcp;

use Blaze\ModelMcp\Audit\LogAuditor;
use Blaze\ModelMcp\Audit\NullAuditor;
use Blaze\ModelMcp\Console\ListModelsCommand;
use Blaze\ModelMcp\Contracts\TenantResolver;
use Blaze\ModelMcp\Contracts\ToolAuditor;
use Blaze\ModelMcp\Registry\ModelRegistry;
use Blaze\ModelMcp\Tenancy\AuthUserTenantResolver;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LaravelModelMcpServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-model-mcp')
            ->hasConfigFile('model-mcp')
            ->hasCommand(ListModelsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ModelRegistry::class);

        $this->app->bind(TenantResolver::class, function (Application $app): TenantResolver {
            /** @var class-string<TenantResolver> $class */
            $class = $app['config']->get('model-mcp.tenancy.resolver', AuthUserTenantResolver::class);

            return $app->make($class);
        });

        $this->app->bind(ToolAuditor::class, function (Application $app): ToolAuditor {
            if (! $app['config']->get('model-mcp.audit.enabled', true)) {
                return $app->make(NullAuditor::class);
            }

            /** @var class-string<ToolAuditor> $class */
            $class = $app['config']->get('model-mcp.audit.logger', LogAuditor::class);

            return $app->make($class);
        });
    }
}
