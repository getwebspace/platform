<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\AbstractMcpTool;
use Psr\Container\ContainerInterface;

/**
 * @property ContainerInterface $container
 */
trait HasMcpTools
{
    /**
     * Register MCP tool, available via /api/v1/mcp
     */
    protected function addMcpTool(AbstractMcpTool|string $tool): bool
    {
        return $this->container->get('mcp')->register($tool);
    }
}
