<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
final class GetModelTool extends AbstractModelTool
{
    public function operation(): string
    {
        return 'view';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->schemas->viewInput($schema, $this->descriptor);
    }

    protected function describeOperation(): string
    {
        return "Fetch a single {$this->descriptor->label()} by primary key. Honors the viewer's policy and tenant.";
    }

    protected function run(Request $request, ?Authenticatable $user): Response
    {
        $id = $this->requireId($request);

        // Tenant scope is applied in the query: a row in another tenant simply
        // "does not exist", so cross-tenant existence never leaks.
        $model = $this->newQuery()->findOrFail($id);

        $this->authorize($user, $model);

        $this->allow($user, ['id' => $id]);

        return Response::json([
            'data' => $this->present($model, 'detailed', $this->requestedFields($request)),
        ]);
    }
}
