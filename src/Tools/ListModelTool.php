<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
final class ListModelTool extends AbstractModelTool
{
    public function operation(): string
    {
        return 'list';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->schemas->listInput($schema, $this->descriptor);
    }

    protected function describeOperation(): string
    {
        return "List {$this->descriptor->label()} records (paginated). Honors the viewer's policy and tenant.";
    }

    protected function run(Request $request, ?Authenticatable $user): Response
    {
        $this->authorize($user, $this->descriptor->modelClass);

        $query = $this->newQuery();
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

        $this->allow($user, ['count' => count($rows), 'page' => $page]);

        return Response::json([
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
