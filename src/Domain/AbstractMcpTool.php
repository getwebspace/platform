<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Casts\ApiKey\Status as ApiKeyStatus;
use App\Domain\Models\ApiKey;
use App\Domain\Traits\HasParameters;
use Illuminate\Cache\ArrayStore as ArrayCache;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * MCP tool: an action that can be called by an AI client via /api/v1/mcp
 */
abstract class AbstractMcpTool
{
    use HasParameters;

    /**
     * Unique tool name, ex: catalog_product_search
     */
    public const NAME = '';

    /**
     * Human-readable title
     */
    public const TITLE = '';

    /**
     * Description for the model: what the tool does and when to use it
     */
    public const DESCRIPTION = '';

    /**
     * Tool does not modify data
     */
    public const READ_ONLY = true;

    /**
     * Tool changes data or returns private data (orders, users, etc.)
     * Such tools are available only with API key
     */
    public const PRIVATE = false;

    /**
     * API entity the tool works with (see App\Domain\References\ApiEntity),
     * API key must have read or write scope for it
     */
    public const SCOPE = '';

    protected ContainerInterface $container;

    protected ArrayCache $arrayCache;

    protected LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        if (empty(static::NAME)) {
            throw new \RuntimeException('MCP tool name is empty');
        }

        $this->container = $container;
        $this->arrayCache = $container->get(ArrayCache::class);
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * JSON Schema of tool arguments
     */
    public function getInputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    /**
     * Whether the caller may use this tool
     *
     * Same rules as for EntityAction: an API key is checked against its own
     * scopes, without a key only public read-only tools are available
     */
    public function isAllowed(?ApiKey $apiKey): bool
    {
        if ($apiKey === null) {
            return !static::PRIVATE && static::READ_ONLY;
        }
        // tool without scope: private or writing tools only for full access keys
        if (static::SCOPE === '') {
            return (!static::PRIVATE && static::READ_ONLY) || ($apiKey->is_full_access && $apiKey->status === ApiKeyStatus::WORK);
        }

        return $apiKey->can(static::SCOPE, static::READ_ONLY ? 'read' : 'write');
    }

    /**
     * Tool definition for tools/list
     */
    public function getDefinition(): array
    {
        return array_filter([
            'name' => static::NAME,
            'title' => static::TITLE,
            'description' => static::DESCRIPTION,
            'inputSchema' => $this->getInputSchema(),
            'annotations' => [
                'readOnlyHint' => static::READ_ONLY,
            ],
        ]);
    }

    /**
     * Execute tool with passed arguments
     * Returned value will be serialized to JSON
     *
     * @throws \Exception message is returned to the client as tool error
     */
    abstract public function execute(array $args = []): mixed;
}
