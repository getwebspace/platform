<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\AbstractMcpPrompt;
use App\Domain\AbstractMcpResource;
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

    /**
     * Register MCP prompt (a ready scenario for the operator)
     */
    protected function addMcpPrompt(AbstractMcpPrompt|string $prompt): bool
    {
        return $this->container->get('mcp')->registerPrompt($prompt);
    }

    /**
     * Register MCP resource (reference data as context for the model)
     */
    protected function addMcpResource(AbstractMcpResource|string $resource): bool
    {
        return $this->container->get('mcp')->registerResource($resource);
    }
}
