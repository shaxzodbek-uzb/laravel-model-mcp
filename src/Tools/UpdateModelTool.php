<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
final class UpdateModelTool extends AbstractModelTool
{
    public function operation(): string
    {
        return 'update';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->schemas->updateInput($schema, $this->descriptor);
    }

    protected function describeOperation(): string
    {
        return "Update an existing {$this->descriptor->label()} by primary key. Requires the 'update' policy ability.";
    }

    protected function run(Request $request, ?Authenticatable $user): Response
    {
        $id = $this->requireId($request);

        $model = $this->newQuery()->findOrFail($id);

        $this->authorize($user, $model);

        $model->fill($this->writableInput($request));

        // Defense in depth: re-stamp the tenant so an update can never move the
        // row out of the current tenant even if the column slipped into input.
        $this->tenant->stamp($model, $this->descriptor);

        $model->save();

        $this->allow($user, ['id' => $id]);

        return Response::json([
            'data' => $this->present($model->refresh(), 'detailed', null),
            'updated' => true,
        ]);
    }
}
