<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\ApiKey;
use App\Domain\Traits\ChecksMcpAccess;
use App\Domain\Traits\HasParameters;
use Illuminate\Cache\ArrayStore as ArrayCache;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * MCP tool: an action that can be called by an AI client via /api/v1/mcp
 */
abstract class AbstractMcpTool
{
    use ChecksMcpAccess;
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

    /**
     * A writing tool can be run with dry_run=true: everything is done in a transaction that is rolled back,
     * the caller sees the result without anything saved. Turn off for tools with effects outside of the database
     */
    public const DRY_RUN = true;

    /**
     * Page size for list tools
     */
    public const LIMIT_DEFAULT = 20;
    public const LIMIT_MAX = 100;

    protected ContainerInterface $container;

    protected ArrayCache $arrayCache;

    protected LoggerInterface $logger;

    /**
     * API key of the current request, null when called without a key
     */
    protected ?ApiKey $apiKey = null;

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

    public function setApiKey(?ApiKey $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Whether the caller may read the entity (see App\Domain\References\ApiEntity)
     * Useful for tools that combine data of several entities
     */
    protected function canRead(string $entity): bool
    {
        return $this->apiKey !== null && $this->apiKey->can($entity, 'read');
    }

    /**
     * JSON Schema properties limit/offset for list tools
     */
    protected function paginationSchema(): array
    {
        return [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => static::LIMIT_MAX, 'default' => static::LIMIT_DEFAULT],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
        ];
    }

    /**
     * Integer argument within a range: a value outside of it is an error the client can see,
     * not something silently cut
     */
    protected function intArgument(array $args, string $name, int $default, int $min, int $max): int
    {
        if (!array_key_exists($name, $args) || $args[$name] === null || $args[$name] === '') {
            return $default;
        }
        if (!is_numeric($args[$name]) || (int) $args[$name] != $args[$name]) {
            throw new \InvalidArgumentException("Field {$name} must be an integer");
        }

        $value = (int) $args[$name];

        if ($value < $min || $value > $max) {
            throw new \InvalidArgumentException("Field {$name} must be from {$min}" . ($max === PHP_INT_MAX ? ' and up' : " to {$max}"));
        }

        return $value;
    }

    private function withDryRun(array $schema): array
    {
        if (static::READ_ONLY || !static::DRY_RUN || !is_array($schema['properties'] ?? null)) {
            return $schema;
        }

        $schema['properties']['dry_run'] = [
            'type' => 'boolean',
            'default' => false,
            'description' => 'true - do everything but save nothing: you get the result the change would have, then show it to the operator',
        ];

        return $schema;
    }

    /**
     * Page of a list: $read gets limit/offset for service read() and returns rows,
     * one extra row is requested to know if there is a next page without count()
     *
     * @param callable(int $limit, int $offset): Collection $read
     * @param callable(mixed $row): array                   $map  compact representation of a row
     */
    protected function paginate(array $args, callable $read, callable $map): array
    {
        $limit = $this->intArgument($args, 'limit', static::LIMIT_DEFAULT, 1, static::LIMIT_MAX);
        $offset = $this->intArgument($args, 'offset', 0, 0, PHP_INT_MAX);

        $rows = collect($read($limit + 1, $offset))->values();

        return [
            'items' => $rows->take($limit)->map($map)->values()->all(),
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $rows->count() > $limit,
        ];
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
            'inputSchema' => $this->withDryRun($this->getInputSchema()),
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
