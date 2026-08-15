<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Describes the shape of an exposed model so an agent can stop guessing.
 *
 * Without this, the only way to learn a model's fields is to call `list` and
 * read whatever comes back — which needs at least one row to exist, needs the
 * caller to be allowed to see it, and still says nothing about which fields are
 * writable, which are required, or what a date column expects. Every one of
 * those unknowns turns into a failed `create` and a retry.
 *
 * It is deliberately **metadata only**. It reads the model's schema and this
 * package's own configuration; it never queries a row, so it cannot leak data
 * and needs no tenant scope. It is still policy-gated on `viewAny`: which models
 * exist, and what columns they have, is itself information not everyone should
 * have.
 */
#[IsReadOnly]
final class DescribeModelTool extends AbstractModelTool
{
    public function operation(): string
    {
        return 'describe';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function describeOperation(): string
    {
        return "Describe the fields, types and available operations of {$this->descriptor->label()}. "
            .'Call this before create or update to learn which fields are writable and what they expect. '
            .'Returns schema metadata only — it reads no rows.';
    }

    protected function run(Request $request, ?Authenticatable $user): Response
    {
        // Gate on the class, not an instance: there is no row involved.
        $this->authorize($user, $this->descriptor->modelClass);

        $this->allow($user);

        return Response::json([
            'model' => $this->descriptor->label(),
            'key' => [
                'name' => $this->descriptor->keyName(),
                'type' => $this->descriptor->introspector()->keyType(),
            ],
            'operations' => array_values($this->descriptor->operations),
            'fields' => $this->fields(),
            'notes' => $this->notes(),
        ]);
    }

    /**
     * One entry per readable field, marking what may be written.
     *
     * Hidden attributes are omitted exactly as they are from every other
     * response, so `describe` can never become the way to discover a column the
     * model deliberately hides.
     *
     * @return list<array<string, mixed>>
     */
    private function fields(): array
    {
        $introspector = $this->descriptor->introspector();

        $hidden = (bool) $this->config->get('model-mcp.fields.respect_hidden', true)
            ? $introspector->hidden()
            : [];
        $hidden = array_merge(
            $hidden,
            (array) $this->config->get('model-mcp.fields.always_hidden', []),
        );

        $writable = $introspector->fillable();
        $casts = $introspector->casts();
        $key = $this->descriptor->keyName();

        $fields = [];

        foreach ($introspector->columnNames() as $column) {
            if (in_array($column, $hidden, true)) {
                continue;
            }

            $entry = [
                'name' => $column,
                'type' => $introspector->columnType($column) ?? 'unknown',
                'writable' => in_array($column, $writable, true),
            ];

            if ($column === $key) {
                $entry['primary_key'] = true;
            }

            if ($introspector->hasColumnInfo()) {
                $entry['nullable'] = $introspector->isNullable($column);

                // Required on create = writable, not nullable, no default, not
                // auto-incrementing. That is the question an agent is actually
                // asking, so answer it rather than making it infer.
                $entry['required_on_create'] = $entry['writable']
                    && ! $introspector->isNullable($column)
                    && ! $introspector->hasDefault($column)
                    && ! $introspector->isAutoIncrement($column);
            }

            if (isset($casts[$column])) {
                $entry['cast'] = $casts[$column];
            }

            $fields[] = $entry;
        }

        return $fields;
    }

    /**
     * Caveats the agent should know before trusting the list above.
     *
     * @return list<string>
     */
    private function notes(): array
    {
        $notes = [];

        if (! $this->descriptor->introspector()->hasColumnInfo()) {
            $notes[] = 'Column metadata is unavailable on this connection, so nullability, defaults '
                .'and required-on-create are omitted rather than guessed.';
        }

        if ($this->descriptor->tenantColumn !== null) {
            $notes[] = sprintf(
                'Rows are scoped by "%s"; it is set automatically and cannot be supplied.',
                $this->descriptor->tenantColumn,
            );
        }

        $notes[] = 'Every operation is still checked against the policy and tenant scope — a field '
            .'appearing here does not mean the current user may read or write it.';

        return $notes;
    }
}
