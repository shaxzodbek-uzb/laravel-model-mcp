<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Console;

use Blaze\ModelMcp\Registry\ModelRegistry;
use Blaze\ModelMcp\Tools\ToolFactory;
use Illuminate\Console\Command;

/**
 * Lists the models exposed over MCP and the tools generated for each — a quick
 * audit of exactly what an MCP client can reach.
 */
final class ListModelsCommand extends Command
{
    protected $signature = 'model-mcp:list';

    protected $description = 'List the Eloquent models exposed over MCP and their generated tools';

    public function handle(ModelRegistry $registry, ToolFactory $factory): int
    {
        $descriptors = $registry->descriptors();

        if ($descriptors === []) {
            $this->components->warn('No models are exposed. Add them to the `model-mcp.models` config array.');

            return self::SUCCESS;
        }

        $tools = $factory->make();

        $rows = [];

        foreach ($tools as $tool) {
            $rows[] = [
                $tool->name(),
                $tool->descriptor->modelClass,
                $tool->operation(),
            ];
        }

        $this->table(['Tool', 'Model', 'Operation'], $rows);

        $this->components->info(sprintf(
            '%d model(s), %d tool(s) exposed.',
            count($descriptors),
            count($tools),
        ));

        return self::SUCCESS;
    }
}
