<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Registry;

use Blaze\ModelMcp\Support\ModelIntrospector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The resolved exposure settings for a single Eloquent model: which operations
 * are exposed, its tenant column, an optional explicit policy, and the tool
 * naming stem.
 */
final class ModelDescriptor
{
    public const OPERATIONS = ['list', 'view', 'create', 'update', 'delete', 'search'];

    private ?ModelIntrospector $introspector = null;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $operations
     * @param  class-string|null  $policyClass
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly array $operations,
        public readonly ?string $tenantColumn = null,
        public readonly ?string $policyClass = null,
        public readonly ?string $name = null,
    ) {
        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException(
                "[{$modelClass}] is not an Eloquent model and cannot be exposed over MCP.",
            );
        }
    }

    public function newModel(): Model
    {
        return new $this->modelClass;
    }

    public function introspector(): ModelIntrospector
    {
        return $this->introspector ??= new ModelIntrospector($this->modelClass);
    }

    public function keyName(): string
    {
        return $this->introspector()->keyName();
    }

    /**
     * The snake_case stem used to build tool names, e.g. "blog_post".
     */
    public function stem(): string
    {
        return $this->name ?? Str::snake(class_basename($this->modelClass));
    }

    /**
     * A human-friendly label, e.g. "Blog Post".
     */
    public function label(): string
    {
        return Str::headline(class_basename($this->modelClass));
    }

    public function exposes(string $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }
}
