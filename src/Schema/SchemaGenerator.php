<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Schema;

use Blaze\ModelMcp\Registry\ModelDescriptor;
use Blaze\ModelMcp\Support\ModelIntrospector;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * Builds the JSON Schema argument map for each generated tool operation. Every
 * method returns an `array<string, Type>` as laravel/mcp's Tool::schema()
 * expects.
 */
final class SchemaGenerator
{
    public function __construct(
        private readonly TypeMapper $types,
        private readonly Config $config,
    ) {
        //
    }

    /**
     * Attributes a create/update tool may write: the model's fillable set,
     * minus the primary key and any globally hidden fields.
     *
     * @return list<string>
     */
    public function writableAttributes(ModelDescriptor $descriptor): array
    {
        $model = $descriptor->introspector();

        $blocked = array_merge(
            [$model->keyName()],
            $this->alwaysHidden(),
            $this->tenantColumns($descriptor),
        );

        return array_values(array_filter(
            $model->fillable(),
            static fn (string $attribute): bool => ! in_array($attribute, $blocked, true),
        ));
    }

    /**
     * Tenant columns that must never be client-writable: the per-model override
     * AND the global default column whenever explicit tenancy is enabled — the
     * same resolution TenantScope uses, so a row can never be moved tenants.
     *
     * @return list<string>
     */
    private function tenantColumns(ModelDescriptor $descriptor): array
    {
        $columns = [];

        if ($descriptor->tenantColumn !== null) {
            $columns[] = $descriptor->tenantColumn;
        }

        if ((bool) $this->config->get('model-mcp.tenancy.enabled', false)) {
            $columns[] = (string) $this->config->get('model-mcp.tenancy.column', 'tenant_id');
        }

        return $columns;
    }

    /**
     * @return array<string, Type>
     */
    public function createInput(JsonSchema $schema, ModelDescriptor $descriptor): array
    {
        return $this->writableSchema($schema, $descriptor, requireMandatory: true);
    }

    /**
     * Writable attributes that must be present on create (non-nullable, no
     * default). Empty when the table schema cannot be introspected.
     *
     * @return list<string>
     */
    public function requiredCreateAttributes(ModelDescriptor $descriptor): array
    {
        $model = $descriptor->introspector();

        return array_values(array_filter(
            $this->writableAttributes($descriptor),
            fn (string $attribute): bool => $this->isMandatory($model, $attribute),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function updateInput(JsonSchema $schema, ModelDescriptor $descriptor): array
    {
        return array_merge(
            ['id' => $this->identifier($schema, $descriptor)],
            $this->writableSchema($schema, $descriptor, requireMandatory: false),
        );
    }

    /**
     * @return array<string, Type>
     */
    public function identifierInput(JsonSchema $schema, ModelDescriptor $descriptor): array
    {
        return ['id' => $this->identifier($schema, $descriptor)];
    }

    /**
     * @return array<string, Type>
     */
    public function viewInput(JsonSchema $schema, ModelDescriptor $descriptor): array
    {
        return [
            'id' => $this->identifier($schema, $descriptor),
            'fields' => $this->fields($schema),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function listInput(JsonSchema $schema, ModelDescriptor $descriptor): array
    {
        return [
            'page' => $schema->integer()->min(1)->default(1)
                ->description('1-based page number.'),
            'per_page' => $schema->integer()->min(1)->max($this->maxPerPage())
                ->default($this->defaultPerPage())
                ->description("Rows per page (max {$this->maxPerPage()})."),
            'sort' => $schema->string()
                ->description('Column to sort by. Prefix with "-" for descending, e.g. "-created_at".'),
            'fields' => $this->fields($schema),
            'response_format' => $schema->string()->enum(['concise', 'detailed'])
                ->description('"concise" returns the key plus a label column; "detailed" returns every visible field.'),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function searchInput(JsonSchema $schema, ModelDescriptor $descriptor): array
    {
        return array_merge(
            ['q' => $schema->string()->required()->min(1)
                ->description('Text to match against the model\'s textual columns.')],
            $this->listInput($schema, $descriptor),
        );
    }

    /**
     * @return array<string, Type>
     */
    private function writableSchema(JsonSchema $schema, ModelDescriptor $descriptor, bool $requireMandatory): array
    {
        $model = $descriptor->introspector();
        $properties = [];

        foreach ($this->writableAttributes($descriptor) as $attribute) {
            $type = $this->types->map($schema, $model, $attribute);

            if ($requireMandatory && $this->isMandatory($model, $attribute)) {
                $type->required();
            }

            $properties[$attribute] = $type;
        }

        return $properties;
    }

    private function isMandatory(ModelIntrospector $model, string $attribute): bool
    {
        // Only assert "required" when the schema is known and proves it: a
        // non-nullable column with no default. Otherwise stay permissive.
        if (! $model->hasColumnInfo() || ! in_array($attribute, $model->columnNames(), true)) {
            return false;
        }

        return ! $model->isNullable($attribute)
            && ! $model->hasDefault($attribute)
            && ! $model->isAutoIncrement($attribute);
    }

    private function identifier(JsonSchema $schema, ModelDescriptor $descriptor): Type
    {
        $type = $descriptor->introspector()->keyType() === 'int'
            ? $schema->integer()
            : $schema->string();

        return $type->required()->description('The primary key of the target record.');
    }

    private function fields(JsonSchema $schema): Type
    {
        return $schema->array()
            ->items($schema->string())
            ->description('Optional subset of attributes to return. Omit for all visible fields.');
    }

    /**
     * @return list<string>
     */
    private function alwaysHidden(): array
    {
        /** @var list<string> */
        return $this->config->get('model-mcp.fields.always_hidden', []);
    }

    private function defaultPerPage(): int
    {
        return (int) $this->config->get('model-mcp.pagination.default_per_page', 25);
    }

    private function maxPerPage(): int
    {
        return (int) $this->config->get('model-mcp.pagination.max_per_page', 100);
    }
}
