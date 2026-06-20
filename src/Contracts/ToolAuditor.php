<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Contracts;

use Blaze\ModelMcp\Audit\ToolCallEvent;

/**
 * Records every generated tool call — allowed, denied or errored.
 *
 * Swap the default LogAuditor for your own implementation (e.g. persisting to
 * an `mcp_audit_log` table) via the `model-mcp.audit.logger` config key.
 */
interface ToolAuditor
{
    public function record(ToolCallEvent $event): void;
}
