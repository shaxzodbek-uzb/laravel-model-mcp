<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tests;

use Blaze\ModelMcp\LaravelModelMcpServiceProvider;
use Blaze\ModelMcp\Registry\ModelRegistry;
use Blaze\ModelMcp\Tests\Fixtures\Models\Post;
use Blaze\ModelMcp\Tests\Fixtures\Models\Tag;
use Blaze\ModelMcp\Tests\Fixtures\Models\User;
use Blaze\ModelMcp\Tests\Fixtures\Policies\PostPolicy;
use Blaze\ModelMcp\Tools\AbstractModelTool;
use Blaze\ModelMcp\Tools\ToolFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->app->make(ModelRegistry::class)->flush();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            LaravelModelMcpServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $config->set('model-mcp.models', [
            Post::class => ['policy' => PostPolicy::class, 'tenant_column' => 'team_id'],
            Tag::class,
        ]);

        $config->set('model-mcp.fields.always_hidden', ['password', 'remember_token', 'secret_notes']);
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('published')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('secret_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Resolve a generated tool instance by its MCP name.
     */
    protected function mcpTool(string $name): AbstractModelTool
    {
        foreach ($this->app->make(ToolFactory::class)->make() as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        throw new RuntimeException("Generated tool [{$name}] was not found.");
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'tenant_id' => 1,
            'password' => 'secret',
        ], $attributes));
    }
}
