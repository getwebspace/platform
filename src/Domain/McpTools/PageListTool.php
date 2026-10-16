<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Models\Page;
use App\Domain\Service\Page\PageService;

class PageListTool extends AbstractMcpTool
{
    public const NAME = 'page_list';
    public const TITLE = 'Pages';
    public const DESCRIPTION = 'Lists static pages of the site (about, contacts, terms...) with address and link. Search by title or address. Each item has the SEO meta (title, description, keywords), so a list is enough for an SEO audit; \'total\' is the number of pages. Use page_get for the content.';
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

        return ['total' => $pageService->count($filter)] + $this->paginate(
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
                'date' => datetime($page->date)->format('Y-m-d H:i'),
                'meta' => $this->meta($page->meta),
                'url' => $homepage . '/' . ltrim((string) $page->address, '/'),
            ]
        );
    }

    /**
     * SEO fields as they are (empty ones stay empty, that is what an audit looks for)
     *
     * @return array{title: string, description: string, keywords: string}
     */
    private function meta(mixed $meta): array
    {
        $meta = (array) $meta;

        return [
            'title' => (string) ($meta['title'] ?? ''),
            'description' => (string) ($meta['description'] ?? ''),
            'keywords' => (string) ($meta['keywords'] ?? ''),
        ];
    }
}
