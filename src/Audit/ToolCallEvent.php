<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Audit;

/**
 * An immutable record of a single MCP tool invocation.
 */
final class ToolCallEvent
{
    public const OUTCOME_ALLOWED = 'allowed';

    public const OUTCOME_DENIED = 'denied';

    public const OUTCOME_ERROR = 'error';

    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $toolName,
        public readonly string $operation,
        public readonly string $modelClass,
        public readonly string $outcome,
        public readonly mixed $userId = null,
        public readonly ?string $reason = null,
        public readonly array $context = [],
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tool' => $this->toolName,
            'operation' => $this->operation,
            'model' => $this->modelClass,
            'outcome' => $this->outcome,
            'user_id' => $this->userId,
            'reason' => $this->reason,
            'context' => $this->context,
        ];
    }
}
