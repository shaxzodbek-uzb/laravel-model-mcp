<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Audit;

use Blaze\ModelMcp\Contracts\ToolAuditor;

/**
 * Discards all audit events. Bound when `model-mcp.audit.enabled` is false.
 */
final class NullAuditor implements ToolAuditor
{
    public function record(ToolCallEvent $event): void
    {
        //
    }
}
