<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\ChecksMcpAccess;
use App\Domain\Traits\HasParameters;
use Psr\Container\ContainerInterface;

/**
 * MCP resource: reference data the AI client can load as context (catalog structure, statuses, rules)
 * Read only. Access works like for tools: SCOPE is an API entity the key must be able to read
 */
abstract class AbstractMcpResource
{
    use ChecksMcpAccess;
    use HasParameters;

    /**
     * Unique address, ex: platform://catalog/categories
     */
    public const URI = '';

    public const NAME = '';

    public const TITLE = '';

    public const DESCRIPTION = '';

    public const MIME = 'application/json';

    public const SCOPE = '';

    public const PRIVATE = false;

    public const READ_ONLY = true;

    protected ContainerInterface $container;

    protected \Illuminate\Cache\ArrayStore $arrayCache;

    public function __construct(ContainerInterface $container)
    {
        if (empty(static::URI) || empty(static::NAME)) {
            throw new \RuntimeException('MCP resource uri or name is empty');
        }

        $this->container = $container;
        $this->arrayCache = $container->get(\Illuminate\Cache\ArrayStore::class);
    }

    public function getDefinition(): array
    {
        return array_filter([
            'uri' => static::URI,
            'name' => static::NAME,
            'title' => static::TITLE,
            'description' => static::DESCRIPTION,
            'mimeType' => static::MIME,
        ]);
    }

    /**
     * Content as text (JSON for application/json)
     */
    abstract public function read(): string;

    protected function json(mixed $data): string
    {
        return json_encode(array_serialize($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
