<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Models\Page;
use App\Domain\Service\Page\PageService;

class PageListTool extends AbstractMcpTool
{
    public const NAME = 'page_list';
    public const TITLE = 'Pages';
    public const DESCRIPTION = 'Lists static pages of the site (about, contacts, terms...) with address and link. Search by title or address. Use page_get for the content.';
    public const SCOPE = 'page';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Part of title or address'],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $filter = [];

        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            $filter['search'] = $search;
        }

        /** @var PageService $pageService */
        $pageService = $this->container->get(PageService::class);
        $homepage = rtrim((string) $this->parameter('common_homepage', ''), '/');

        return $this->paginate(
            $args,
            fn (int $limit, int $offset) => $pageService->read($filter + [
                'with' => [],
                'order' => ['title' => 'asc'],
                'limit' => $limit,
                'offset' => $offset,
            ]),
            fn (Page $page) => [
                'uuid' => (string) $page->uuid,
                'title' => $page->title,
                'address' => $page->address,
                'type' => $page->type,
                'template' => $page->template,
                'url' => $homepage . '/' . ltrim((string) $page->address, '/'),
            ]
        );
    }
}
