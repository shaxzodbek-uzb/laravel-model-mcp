<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsDestructive]
#[IsIdempotent]
final class DeleteModelTool extends AbstractModelTool
{
    public function operation(): string
    {
        return 'delete';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->schemas->identifierInput($schema, $this->descriptor);
    }

    protected function describeOperation(): string
    {
        return "Delete a {$this->descriptor->label()} by primary key (soft-deletes if the model "
            ."uses the SoftDeletes trait, otherwise permanent). Requires the 'delete' policy ability.";
    }

    protected function run(Request $request, ?Authenticatable $user): Response
    {
        $id = $this->requireId($request);

        $model = $this->newQuery()->findOrFail($id);

        $this->authorize($user, $model);

        $model->delete();

        $this->allow($user, ['id' => $id]);

        return Response::json([
            'deleted' => true,
            'id' => $id,
        ]);
    }
}
