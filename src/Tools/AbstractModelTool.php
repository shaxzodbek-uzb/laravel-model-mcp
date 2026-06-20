<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tools;

use Blaze\ModelMcp\Audit\ToolCallEvent;
use Blaze\ModelMcp\Authorization\PolicyGuard;
use Blaze\ModelMcp\Contracts\ToolAuditor;
use Blaze\ModelMcp\Registry\ModelDescriptor;
use Blaze\ModelMcp\Schema\SchemaGenerator;
use Blaze\ModelMcp\Tenancy\TenantScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Base class for every generated, model-bound MCP tool.
 *
 * Each instance is parameterized by a {@see ModelDescriptor} and runs the same
 * safe pipeline: resolve the user → (tenant-scope the query) → enforce the
 * Laravel Policy → act → audit. Every failure mode is mapped to a controlled
 * `Response::error()` (an MCP isError result) so the agent can recover and no
 * internals leak.
 */
abstract class AbstractModelTool extends Tool
{
    public function __construct(
        public readonly ModelDescriptor $descriptor,
        protected readonly SchemaGenerator $schemas,
        protected readonly PolicyGuard $guard,
        protected readonly TenantScope $tenant,
        protected readonly ToolAuditor $auditor,
        protected readonly Config $config,
    ) {
        $this->name = $this->resolveName();
        $this->title = Str::headline($this->name);
        $this->description = $this->describeOperation();
    }

    /**
     * The CRUD operation this tool performs (list, view, create, update, delete, search).
     */
    abstract public function operation(): string;

    /**
     * A one-line, human description of what the tool does.
     */
    abstract protected function describeOperation(): string;

    /**
     * Perform the operation. May throw — handle() maps exceptions to errors.
     */
    abstract protected function run(Request $request, ?Authenticatable $user): Response;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        try {
            return $this->run($request, $user);
        } catch (AuthenticationException) {
            return $this->denied($user, 'unauthenticated', 'Authentication is required to use this tool.');
        } catch (AuthorizationException $e) {
            return $this->denied($user, $e->getMessage(), $this->forbiddenMessage());
        } catch (ValidationException $e) {
            $this->audit(ToolCallEvent::OUTCOME_ERROR, $user, 'validation');

            return Response::error($this->validationMessage($e));
        } catch (ModelNotFoundException) {
            $this->audit(ToolCallEvent::OUTCOME_ERROR, $user, 'not_found');

            return Response::error("No {$this->descriptor->label()} was found for the given id.");
        } catch (Throwable $e) {
            report($e);
            $this->audit(ToolCallEvent::OUTCOME_ERROR, $user, 'exception');

            $message = (bool) $this->config->get('app.debug', false)
                ? 'The request could not be completed: '.$e->getMessage()
                : 'The request could not be completed.';

            return Response::error($message);
        }
    }

    /**
     * @param  Model|class-string<Model>  $target
     *
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    protected function authorize(?Authenticatable $user, Model|string $target): void
    {
        $this->guard->authorize($user, $this->descriptor, $this->guard->abilityFor($this->operation()), $target);
    }

    /**
     * A fresh, tenant-scoped query for the bound model.
     *
     * @return Builder<Model>
     */
    protected function newQuery(): Builder
    {
        return $this->tenant->apply($this->descriptor->newModel()->newQuery(), $this->descriptor);
    }

    /**
     * @throws ValidationException
     */
    protected function requireId(Request $request): mixed
    {
        // Constrain to a scalar of the key's type so an array id can never reach
        // findOrFail() (which would return a Collection, not a Model).
        $rule = $this->descriptor->introspector()->keyType() === 'int' ? 'integer' : 'string';

        $request->validate(['id' => ['required', $rule]]);

        return $request->get('id');
    }

    /**
     * The subset of request input that may be written to the model.
     *
     * @return array<string, mixed>
     */
    protected function writableInput(Request $request): array
    {
        return Arr::only($request->all(), $this->schemas->writableAttributes($this->descriptor));
    }

    /**
     * @param  list<string>|null  $fields
     * @return array<string, mixed>
     */
    protected function present(Model $model, string $format, ?array $fields): array
    {
        if (! (bool) $this->config->get('model-mcp.fields.respect_hidden', true)) {
            $model = (clone $model)->makeVisible($model->getHidden());
        }

        $data = $model->attributesToArray();

        foreach ((array) $this->config->get('model-mcp.fields.always_hidden', []) as $hidden) {
            unset($data[$hidden]);
        }

        $key = $this->descriptor->keyName();

        if (! empty($fields)) {
            $keep = array_values(array_unique([$key, ...$fields]));

            return array_intersect_key($data, array_flip($keep));
        }

        if ($format === 'concise') {
            $label = $this->labelColumn($data, $key);

            return array_intersect_key($data, array_flip(array_values(array_unique([$key, $label]))));
        }

        return $data;
    }

    protected function perPage(Request $request): int
    {
        $max = (int) $this->config->get('model-mcp.pagination.max_per_page', 100);
        $default = (int) $this->config->get('model-mcp.pagination.default_per_page', 25);
        $value = $request->get('per_page');

        return max(1, min(is_numeric($value) ? (int) $value : $default, $max));
    }

    protected function responseFormat(Request $request): string
    {
        $value = $request->get('response_format');

        return in_array($value, ['concise', 'detailed'], true)
            ? $value
            : (string) $this->config->get('model-mcp.response.default_format', 'concise');
    }

    /**
     * @return list<string>|null
     */
    protected function requestedFields(Request $request): ?array
    {
        $fields = $request->get('fields');

        if (! is_array($fields) || $fields === []) {
            return null;
        }

        return array_values(array_filter($fields, 'is_string'));
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function applySort(Builder $query, mixed $sort): void
    {
        if (! is_string($sort) || $sort === '') {
            return;
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, $this->sortableColumns(), true)) {
            $query->orderBy($column, $direction);
        }
    }

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        $introspector = $this->descriptor->introspector();

        $columns = $introspector->columnNames();

        if ($columns === []) {
            $columns = array_values(array_unique([
                $introspector->keyName(),
                ...$introspector->fillable(),
            ]));
        }

        return $columns;
    }

    /**
     * Textual columns a search may match against.
     *
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        $introspector = $this->descriptor->introspector();
        $hidden = array_merge($introspector->hidden(), (array) $this->config->get('model-mcp.fields.always_hidden', []));

        if (! $introspector->hasColumnInfo()) {
            return array_values(array_filter(
                $introspector->fillable(),
                static fn (string $column): bool => ! in_array($column, $hidden, true),
            ));
        }

        $textual = [];

        foreach ($introspector->columnNames() as $column) {
            if (in_array($column, $hidden, true)) {
                continue;
            }

            $type = strtolower((string) $introspector->columnType($column));

            if (str_contains($type, 'char') || str_contains($type, 'text') || $type === 'string') {
                $textual[] = $column;
            }
        }

        return $textual;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function allow(?Authenticatable $user, array $context = []): void
    {
        $this->audit(ToolCallEvent::OUTCOME_ALLOWED, $user, null, $context);
    }

    private function denied(?Authenticatable $user, ?string $reason, string $message): Response
    {
        $this->audit(ToolCallEvent::OUTCOME_DENIED, $user, $reason);

        return Response::error($message);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function audit(string $outcome, ?Authenticatable $user, ?string $reason = null, array $context = []): void
    {
        $this->auditor->record(new ToolCallEvent(
            toolName: $this->name(),
            operation: $this->operation(),
            modelClass: $this->descriptor->modelClass,
            outcome: $outcome,
            userId: $user?->getAuthIdentifier(),
            reason: $reason,
            context: $context,
        ));
    }

    private function forbiddenMessage(): string
    {
        return "You are not authorized to {$this->operation()} {$this->descriptor->label()} records.";
    }

    private function validationMessage(ValidationException $e): string
    {
        $first = collect($e->errors())->flatten()->first();

        return is_string($first) ? $first : 'The provided arguments were invalid.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function labelColumn(array $data, string $key): string
    {
        foreach (['name', 'title', 'label', 'subject', 'slug', 'email'] as $candidate) {
            if (array_key_exists($candidate, $data)) {
                return $candidate;
            }
        }

        return $key;
    }

    private function resolveName(): string
    {
        $scheme = (string) $this->config->get('model-mcp.naming.scheme', 'verb_model');
        $pluralize = (bool) $this->config->get('model-mcp.naming.pluralize', true);

        $stem = $this->descriptor->stem();
        $plural = in_array($this->operation(), ['list', 'search'], true);
        $noun = $pluralize ? ($plural ? Str::plural($stem) : Str::singular($stem)) : $stem;
        $verb = $this->operation() === 'view' ? 'get' : $this->operation();

        return $scheme === 'model_verb' ? "{$noun}_{$verb}" : "{$verb}_{$noun}";
    }
}
