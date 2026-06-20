<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Schema;

use Blaze\ModelMcp\Support\ModelIntrospector;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use ReflectionEnum;
use Throwable;

/**
 * Maps an Eloquent attribute (via its cast, then its database column type) onto
 * a laravel/mcp JSON Schema {@see Type}. Falls back to a permissive string when
 * nothing can be inferred — never throws.
 */
final class TypeMapper
{
    public function map(JsonSchema $schema, ModelIntrospector $model, string $attribute): Type
    {
        $casts = $model->casts();

        if (isset($casts[$attribute])) {
            $type = $this->fromCast($schema, (string) $casts[$attribute]);

            if ($type !== null) {
                return $type;
            }
        }

        $columnType = $model->columnType($attribute);

        if ($columnType !== null) {
            return $this->fromColumnType($schema, $columnType);
        }

        return $schema->string();
    }

    private function fromCast(JsonSchema $schema, string $cast): ?Type
    {
        // Parameterised casts: "decimal:2", "datetime:Y-m-d", "encrypted:array".
        $base = strtolower(explode(':', $cast, 2)[0]);

        // A backed-enum class-string cast.
        if (enum_exists($cast)) {
            return $this->fromEnum($schema, $cast);
        }

        return match ($base) {
            'int', 'integer', 'timestamp' => $schema->integer(),
            'real', 'float', 'double', 'decimal' => $schema->number(),
            'bool', 'boolean' => $schema->boolean(),
            'array', 'json', 'collection', 'encrypted' => $schema->array(),
            'object' => $schema->object(),
            'date' => $schema->string()->format('date'),
            'datetime', 'immutable_date', 'immutable_datetime', 'custom_datetime' => $schema->string()->format('date-time'),
            'string', 'hashed' => $schema->string(),
            default => null,
        };
    }

    private function fromColumnType(JsonSchema $schema, string $columnType): Type
    {
        $type = strtolower($columnType);

        return match (true) {
            str_contains($type, 'int') => $schema->integer(),
            str_contains($type, 'bool'), $type === 'bit' => $schema->boolean(),
            str_contains($type, 'decimal'),
            str_contains($type, 'numeric'),
            str_contains($type, 'float'),
            str_contains($type, 'double'),
            str_contains($type, 'real') => $schema->number(),
            str_contains($type, 'json') => $schema->array(),
            $type === 'date' => $schema->string()->format('date'),
            str_contains($type, 'timestamp'),
            str_contains($type, 'datetime') => $schema->string()->format('date-time'),
            default => $schema->string(),
        };
    }

    /**
     * @param  class-string  $enumClass
     */
    private function fromEnum(JsonSchema $schema, string $enumClass): Type
    {
        $backing = 'string';

        try {
            $backing = (string) ((new ReflectionEnum($enumClass))->getBackingType()?->getName() ?? 'string');
        } catch (Throwable) {
            // Fall through to a string enum.
        }

        $base = $backing === 'int' ? $schema->integer() : $schema->string();

        return $base->enum($enumClass);
    }
}
