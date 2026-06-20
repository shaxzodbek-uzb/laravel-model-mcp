<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
final class SearchModelTool extends AbstractModelTool
{
    public function operation(): string
    {
        return 'search';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->schemas->searchInput($schema, $this->descriptor);
    }

    protected function describeOperation(): string
    {
        return "Search {$this->descriptor->label()} records by text across their textual columns. Honors the viewer's policy and tenant.";
    }

    protected function run(Request $request, ?Authenticatable $user): Response
    {
        $this->authorize($user, $this->descriptor->modelClass);

        $term = (string) $request->validate(['q' => ['required', 'string', 'min:1']])['q'];

        $columns = $this->searchableColumns();

        $query = $this->newQuery();

        if ($columns !== []) {
            // Escape LIKE metacharacters so "%" or "_" match literally instead of
            // turning the search into a full-table dump.
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $grammar = $query->getQuery()->getGrammar();

            $query->where(function (Builder $builder) use ($columns, $escaped, $grammar): void {
                foreach ($columns as $column) {
                    $builder->orWhereRaw(
                        $grammar->wrap($column)." LIKE ? ESCAPE '\\'",
                        ['%'.$escaped.'%'],
                    );
                }
            });
        }

        $this->applySort($query, $request->get('sort'));

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->get('page', 1));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $format = $this->responseFormat($request);
        $fields = $this->requestedFields($request);

        $rows = array_map(
            fn (Model $model): array => $this->present($model, $format, $fields),
            $paginator->items(),
        );

        $this->allow($user, ['query' => $term, 'count' => count($rows)]);

        return Response::json([
            'query' => $term,
            'data' => $rows,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
