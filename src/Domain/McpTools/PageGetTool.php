<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Service\Page\PageService;

class PageGetTool extends AbstractMcpTool
{
    public const NAME = 'page_get';
    public const TITLE = 'Get page';
    public const DESCRIPTION = 'Returns a static page: content (HTML), SEO, template and the public link. By uuid or address.';
    public const SCOPE = 'page';

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

        $page = $this->container->get(PageService::class)->read($criteria);

        return [
            'uuid' => (string) $page->uuid,
            'title' => $page->title,
            'address' => $page->address,
            'type' => $page->type,
            'template' => $page->template,
            'content' => $page->content,
            'meta' => $page->meta,
            'url' => rtrim((string) $this->parameter('common_homepage', ''), '/') . '/' . ltrim((string) $page->address, '/'),
        ];
    }
}
