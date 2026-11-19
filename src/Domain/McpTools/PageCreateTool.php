<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\Casts\Page\Type as PageType;
use App\Domain\McpTools\Concerns\BuildsAddress;
use App\Domain\McpTools\Concerns\ReadsPageFields;
use App\Domain\McpTools\Concerns\SanitizesHtml;
use App\Domain\Service\Page\PageService;

class PageCreateTool extends AbstractMcpTool
{
    use BuildsAddress;
    use ReadsPageFields;
    use SanitizesHtml;

    public const NAME = 'page_create';
    public const TITLE = 'Create page';
    public const DESCRIPTION = 'Creates a static page. Pages have no draft state - the new page is LIVE on the site at once, so show the operator the final text and get explicit confirmation first. '
        . 'Content is HTML (scripts and event handlers are removed) or plain text. The address is made of the title unless "address" is passed.';
    public const READ_ONLY = false;
    public const SCOPE = 'page';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'title' => $string,
                'content' => ['type' => 'string', 'description' => 'Page content'],
                'address' => $string + ['description' => 'Page address, latin letters, digits and dashes'],
                'meta_title' => $string,
                'meta_description' => $string,
                'meta_keywords' => $string,
            ] + $this->pageFieldsSchema(),
            'required' => ['title'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $title = $this->textArgument($args, 'title');

        if (blank($title)) {
            throw new \InvalidArgumentException('Field title is required');
        }

        $fields = $this->pageFields($args);
        $type = $fields['type'] ?? PageType::HTML;
        $content = (string) ($args['content'] ?? '');

        $page = $this->container->get(PageService::class)->create([
            'title' => $title,
            'address' => $this->buildAddress('', $title, $args['address'] ?? null),
            'content' => $type === PageType::HTML ? $this->sanitizeHtml($content) : $content,
            'meta' => array_map('trim', array_filter([
                'title' => $args['meta_title'] ?? null,
                'description' => $args['meta_description'] ?? null,
                'keywords' => $args['meta_keywords'] ?? null,
            ], fn ($value) => $value !== null)),
        ] + $fields);

        $this->container->get(PubSub::class)->publish('api:page:create', $page);
        $this->logger->notice('Page created via MCP', ['uuid' => (string) $page->uuid]);

        return [
            'uuid' => (string) $page->uuid,
            'title' => $page->title,
            'address' => $page->address,
            'type' => $page->type,
            'template' => $page->template,
            'url' => rtrim((string) $this->parameter('common_homepage', ''), '/') . '/' . ltrim((string) $page->address, '/'),
        ];
    }
}
