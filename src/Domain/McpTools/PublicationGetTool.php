<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\DescribesPublications;
use App\Domain\Service\Publication\PublicationService;

class PublicationGetTool extends AbstractMcpTool
{
    use DescribesPublications;

    public const NAME = 'publication_get';
    public const TITLE = 'Get publication';
    public const DESCRIPTION = 'Returns a publication: short and full text (HTML), SEO, category, state and the public link. By uuid or address.';
    public const PRIVATE = true;
    public const SCOPE = 'publication';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => ['type' => 'string'],
                'address' => ['type' => 'string'],
            ],
            'description' => 'Pass uuid or address',
        ];
    }

    public function execute(array $args = []): mixed
    {
        $criteria = array_filter(array_intersect_key($args, array_flip(['uuid', 'address'])), fn ($value) => is_string($value) && !blank($value));

        if (!$criteria) {
            throw new \InvalidArgumentException('Field uuid or address is required');
        }

        return $this->publicationFull($this->container->get(PublicationService::class)->read($criteria));
    }
}
