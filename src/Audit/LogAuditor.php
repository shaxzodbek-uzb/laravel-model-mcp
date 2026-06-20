<?php

declare(strict_types=1);

namespace Blaze\ModelMcp\Audit;

use Blaze\ModelMcp\Contracts\ToolAuditor;
use Illuminate\Contracts\Config\Repository as Config;
use Psr\Log\LoggerInterface;

/**
 * Writes tool-call audit events to a Laravel log channel.
 *
 * Denied calls are logged at `warning`, everything else at `info`. Allowed and
 * denied logging can be toggled independently via `model-mcp.audit`.
 */
final class LogAuditor implements ToolAuditor
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Config $config,
    ) {
        //
    }

    public function record(ToolCallEvent $event): void
    {
        $shouldLog = match ($event->outcome) {
            ToolCallEvent::OUTCOME_ALLOWED => (bool) $this->config->get('model-mcp.audit.log_allowed', true),
            ToolCallEvent::OUTCOME_DENIED => (bool) $this->config->get('model-mcp.audit.log_denied', true),
            default => true,
        };

        if (! $shouldLog) {
            return;
        }

        $level = $event->outcome === ToolCallEvent::OUTCOME_ALLOWED ? 'info' : 'warning';

        $this->channel()->log($level, 'model-mcp.tool_call', $event->toArray());
    }

    private function channel(): LoggerInterface
    {
        $channel = $this->config->get('model-mcp.audit.channel');

        if ($channel === null || ! method_exists($this->logger, 'channel')) {
            return $this->logger;
        }

        /** @var LoggerInterface */
        return $this->logger->channel($channel);
    }
}
