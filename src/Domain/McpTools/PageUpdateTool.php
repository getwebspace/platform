<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Page\Type as PageType;
use App\Domain\McpTools\Concerns\SanitizesHtml;
use App\Domain\Service\Page\PageService;

class PageUpdateTool extends AbstractMcpTool
{
    use SanitizesHtml;

    public const NAME = 'page_update';
    public const TITLE = 'Update page';
    public const DESCRIPTION = 'Changes a static page: title, content (HTML for html pages, scripts and event handlers are removed), SEO. Pass only the fields to change. '
        . 'Pages have no draft state - the change is LIVE on the site at once. Read the page with page_get, show the operator the new text and get explicit confirmation first. The address is kept unless "address" is passed.';
    public const READ_ONLY = false;
    public const SCOPE = 'page';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'uuid' => $string,
                'title' => $string,
                'content' => ['type' => 'string', 'description' => 'Page content, HTML'],
                'meta_title' => $string,
                'meta_description' => $string,
                'meta_keywords' => $string,
                'address' => $string + ['description' => 'Change the page address (breaks existing links!)'],
            ],
            'required' => ['uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var PageService $pageService */
        $pageService = $this->container->get(PageService::class);
        $page = $pageService->read(['uuid' => $args['uuid']]);

        $data = [];

        if (array_key_exists('title', $args)) {
            if (blank(trim((string) $args['title']))) {
                throw new \InvalidArgumentException('Field title cannot be empty');
            }

            $data['title'] = trim((string) $args['title']);
        }
        if (array_key_exists('content', $args)) {
            $data['content'] = $page->type === PageType::HTML ? $this->sanitizeHtml((string) $args['content']) : (string) $args['content'];
        }

        $meta = array_filter([
            'title' => $args['meta_title'] ?? null,
            'description' => $args['meta_description'] ?? null,
            'keywords' => $args['meta_keywords'] ?? null,
        ], fn ($value) => $value !== null);

        if ($meta) {
            $data['meta'] = array_merge((array) $page->meta, array_map('trim', $meta));
        }

        $changed = array_keys($data);
        $addressChange = !blank($args['address'] ?? null);

        if (!$data && !$addressChange) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        // address generation on update would move the page to another URL
        $data['address'] = $addressChange ? trim((string) $args['address']) : $page->address;

        $page = $pageService->update($page, $data);

        $this->container->get(PubSub::class)->publish('api:page:edit', $page);
        $this->logger->notice('Page updated via MCP', ['uuid' => (string) $page->uuid, 'fields' => $changed]);

        return [
            'uuid' => (string) $page->uuid,
            'title' => $page->title,
            'address' => $page->address,
            'changed_fields' => $addressChange ? [...$changed, 'address'] : $changed,
            'url' => rtrim((string) $this->parameter('common_homepage', ''), '/') . '/' . ltrim((string) $page->address, '/'),
        ];
    }
}
