<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads structural metadata off an Eloquent model without ever instantiating
 * relationships or touching rows. Column metadata uses Laravel 11+ native
 * schema introspection (no doctrine/dbal).
 */
final class ModelIntrospector
{
    private readonly Model $model;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $columns = null;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(private readonly string $modelClass)
    {
        $this->model = new $modelClass;
    }

    public function model(): Model
    {
        return $this->model;
    }

    public function table(): string
    {
        return $this->model->getTable();
    }

    public function keyName(): string
    {
        return $this->model->getKeyName();
    }

    public function keyType(): string
    {
        return $this->model->getKeyType();
    }

    /**
     * @return list<string>
     */
    public function fillable(): array
    {
        return array_values($this->model->getFillable());
    }

    /**
     * @return list<string>
     */
    public function hidden(): array
    {
        return array_values($this->model->getHidden());
    }

    /**
     * @return array<string, mixed>
     */
    public function casts(): array
    {
        return $this->model->getCasts();
    }

    /**
     * Column metadata keyed by column name. Empty when the table is
     * unavailable (e.g. not migrated) — callers must tolerate that.
     *
     * @return array<string, array<string, mixed>>
     */
    public function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        try {
            $columns = Schema::connection($this->model->getConnectionName())
                ->getColumns($this->table());
        } catch (Throwable) {
            return $this->columns = [];
        }

        $keyed = [];

        foreach ($columns as $column) {
            $keyed[$column['name']] = $column;
        }

        return $this->columns = $keyed;
    }

    /**
     * @return list<string>
     */
    public function columnNames(): array
    {
        return array_keys($this->columns());
    }

    public function hasColumnInfo(): bool
    {
        return $this->columns() !== [];
    }

    public function isNullable(string $column): bool
    {
        // Default to nullable (optional) when we cannot prove otherwise.
        return (bool) ($this->columns()[$column]['nullable'] ?? true);
    }

    public function hasDefault(string $column): bool
    {
        return ($this->columns()[$column]['default'] ?? null) !== null;
    }

    public function isAutoIncrement(string $column): bool
    {
        return (bool) ($this->columns()[$column]['auto_increment'] ?? false);
    }

    /**
     * The database column type name (e.g. "varchar", "bigint", "jsonb").
     */
    public function columnType(string $column): ?string
    {
        $meta = $this->columns()[$column] ?? null;

        if ($meta === null) {
            return null;
        }

        return $meta['type_name'] ?? $meta['type'] ?? null;
    }
}
