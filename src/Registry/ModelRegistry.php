<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Registry;

use Blaze\ModelMcp\Attributes\McpModel;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Resolves the set of {@see ModelDescriptor}s to expose, from the explicit
 * allow-list and (optionally) from models tagged with {@see McpModel}.
 */
final class ModelRegistry
{
    /** @var list<ModelDescriptor>|null */
    private ?array $cache = null;

    public function __construct(private readonly Config $config)
    {
        //
    }

    /**
     * @return list<ModelDescriptor>
     */
    public function descriptors(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $descriptors = [];
        $seen = [];

        foreach ($this->configuredModels() as $class => $options) {
            $descriptor = $this->makeDescriptor($class, $options);
            $descriptors[] = $descriptor;
            $seen[$descriptor->modelClass] = true;
        }

        if ($this->discoveryEnabled()) {
            foreach ($this->discoveredModels() as $class => $options) {
                if (isset($seen[$class])) {
                    continue;
                }

                $descriptors[] = $this->makeDescriptor($class, $options);
                $seen[$class] = true;
            }
        }

        return $this->cache = $descriptors;
    }

    public function flush(): void
    {
        $this->cache = null;
    }

    /**
     * @return iterable<class-string<Model>, array<string, mixed>>
     */
    private function configuredModels(): iterable
    {
        /** @var array<int|string, mixed> $models */
        $models = $this->config->get('model-mcp.models', []);

        foreach ($models as $key => $value) {
            if (is_int($key)) {
                /** @var class-string<Model> $class */
                $class = $value;

                yield $class => [];

                continue;
            }

            /** @var class-string<Model> $key */
            yield $key => is_array($value) ? $value : [];
        }
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<string, mixed>  $options
     */
    private function makeDescriptor(string $class, array $options): ModelDescriptor
    {
        return new ModelDescriptor(
            modelClass: $class,
            operations: $this->resolveOperations($options['operations'] ?? null),
            tenantColumn: $options['tenant_column'] ?? null,
            policyClass: $options['policy'] ?? null,
            name: $options['name'] ?? null,
        );
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    private function resolveOperations(?array $requested): array
    {
        /** @var list<string> $default */
        $default = $this->config->get('model-mcp.operations', ModelDescriptor::OPERATIONS);

        $requested ??= $default;

        // A global read-only switch trumps any per-model write operations.
        if ((bool) $this->config->get('model-mcp.read_only', false)) {
            $requested = array_intersect($requested, ['list', 'view', 'search']);
        }

        // Preserve canonical order and drop anything unsupported.
        return array_values(array_filter(
            ModelDescriptor::OPERATIONS,
            static fn (string $operation): bool => in_array($operation, $requested, true),
        ));
    }

    private function discoveryEnabled(): bool
    {
        return (bool) $this->config->get('model-mcp.discovery.enabled', false);
    }

    /**
     * @return iterable<class-string<Model>, array<string, mixed>>
     */
    private function discoveredModels(): iterable
    {
        /** @var list<string> $paths */
        $paths = $this->config->get('model-mcp.discovery.paths', []);

        $existing = array_values(array_filter($paths, 'is_dir'));

        if ($existing === []) {
            return;
        }

        foreach (Finder::create()->files()->in($existing)->name('*.php') as $file) {
            $class = $this->classFromFile($file->getRealPath() ?: $file->getPathname());

            if ($class === null || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $attribute = $this->mcpAttribute($class);

            if ($attribute === null) {
                continue;
            }

            yield $class => [
                'operations' => $attribute->operations,
                'tenant_column' => $attribute->tenantColumn,
                'policy' => $attribute->policy,
                'name' => $attribute->name,
            ];
        }
    }

    /**
     * @param  class-string  $class
     */
    private function mcpAttribute(string $class): ?McpModel
    {
        try {
            $attributes = (new ReflectionClass($class))->getAttributes(McpModel::class);
        } catch (Throwable) {
            return null;
        }

        return isset($attributes[0]) ? $attributes[0]->newInstance() : null;
    }

    /**
     * Derive the fully-qualified class name from a PHP file via token parsing,
     * without executing it.
     *
     * @return class-string|null
     */
    private function classFromFile(string $path): ?string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $tokens = token_get_all($contents);
        $namespace = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = $this->readName($tokens, $i + 1);
            }

            if (is_array($token) && $token[0] === T_CLASS) {
                // Skip "::class" and anonymous classes.
                $prev = $tokens[$i - 1] ?? null;

                if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                    continue;
                }

                $name = $this->readName($tokens, $i + 1);

                if ($name === '') {
                    continue;
                }

                /** @var class-string $fqcn */
                $fqcn = $namespace === '' ? $name : $namespace.'\\'.$name;

                return class_exists($fqcn) ? $fqcn : null;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{0:int,1:string,2:int}|string>  $tokens
     */
    private function readName(array $tokens, int $start): string
    {
        $name = '';
        $count = count($tokens);

        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                // T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, namespace separators.
                if (in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $name .= $token[1];

                    continue;
                }

                if ($token[0] === T_WHITESPACE && $name === '') {
                    continue;
                }
            }

            break;
        }

        return trim($name, '\\');
    }
}
