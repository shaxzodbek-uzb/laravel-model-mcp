<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

final class CreateModelTool extends AbstractModelTool
{
    public function operation(): string
    {
        return 'create';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->schemas->createInput($schema, $this->descriptor);
    }

    protected function describeOperation(): string
    {
        return "Create a new {$this->descriptor->label()}. Requires the 'create' policy ability.";
    }

    protected function run(Request $request, ?Authenticatable $user): Response
    {
        $this->authorize($user, $this->descriptor->modelClass);

        $required = $this->schemas->requiredCreateAttributes($this->descriptor);

        if ($required !== []) {
            $request->validate(array_fill_keys($required, ['required']));
        }

        $model = $this->descriptor->newModel();
        $model->fill($this->writableInput($request));

        // Force the tenant onto the new record so it can never be created in
        // another tenant's space.
        $this->tenant->stamp($model, $this->descriptor);

        $model->save();

        $this->allow($user, ['id' => $model->getKey()]);

        return Response::json([
            'data' => $this->present($model->refresh(), 'detailed', null),
            'created' => true,
        ]);
    }
}
