<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\DescribesPublications;
use App\Domain\McpTools\Concerns\SanitizesHtml;
use App\Domain\Models\PublicationCategory;
use App\Domain\Service\Publication\PublicationService;

class PublicationCreateTool extends AbstractMcpTool
{
    use DescribesPublications;
    use SanitizesHtml;

    public const NAME = 'publication_create';
    public const TITLE = 'Create publication';
    public const DESCRIPTION = 'Creates a publication (news, article). It is a DRAFT unless "publish_at" is passed: drafts are not shown on the site, publish later with publication_update publish=true. '
        . 'Texts are HTML (scripts and event handlers are removed). Category: ask the operator, categories are listed by publication_list items or in the admin panel. '
        . 'Never publish without explicit confirmation of the final text.';
    public const READ_ONLY = false;
    public const SCOPE = 'publication';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'title' => $string,
                'category_uuid' => $string,
                'short' => ['type' => 'string', 'description' => 'Short text for lists, HTML'],
                'full' => ['type' => 'string', 'description' => 'Full text, HTML'],
                'meta_title' => $string,
                'meta_description' => $string,
                'meta_keywords' => $string,
                'publish_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD HH:MM - show on the site from this moment (leave empty for a draft)'],
            ],
            'required' => ['title', 'category_uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (blank(trim((string) ($args['title'] ?? '')))) {
            throw new \InvalidArgumentException('Field title is required');
        }

        $category = is_string($args['category_uuid'] ?? null) && \Ramsey\Uuid\Uuid::isValid($args['category_uuid'])
            ? PublicationCategory::query()->where('uuid', $args['category_uuid'])->first()
            : null;

        if ($category === null) {
            $titles = PublicationCategory::query()->pluck('title', 'uuid')->map(fn ($title, $uuid) => "{$title} ({$uuid})")->implode(', ');

            throw new \InvalidArgumentException('Category not found, allowed: ' . $titles);
        }

        $date = self::DRAFT_DATE;

        if (!blank($args['publish_at'] ?? null)) {
            $value = trim((string) $args['publish_at']);

            if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value) !== 1 || strtotime($value) === false) {
                throw new \InvalidArgumentException('Field publish_at must be YYYY-MM-DD or YYYY-MM-DD HH:MM');
            }

            $date = datetime($value)->format('Y-m-d H:i:s');
        }

        $publication = $this->container->get(PublicationService::class)->create([
            'title' => trim((string) $args['title']),
            'category_uuid' => (string) $category->uuid,
            // without an address the page cannot be opened; same pattern as the site's address generation
            'address' => implode('/', array_filter([$category->address, trim((string) $args['title'])], fn ($part) => (string) $part !== '')),
            'content' => [
                'short' => $this->sanitizeHtml((string) ($args['short'] ?? '')),
                'full' => $this->sanitizeHtml((string) ($args['full'] ?? '')),
            ],
            'meta' => array_filter([
                'title' => $args['meta_title'] ?? null,
                'description' => $args['meta_description'] ?? null,
                'keywords' => $args['meta_keywords'] ?? null,
            ], fn ($value) => $value !== null),
            'date' => $date,
        ]);

        $this->container->get(PubSub::class)->publish('api:publication:create', $publication);
        $this->logger->notice('Publication created via MCP', ['uuid' => (string) $publication->uuid, 'draft' => $date === self::DRAFT_DATE]);

        return $this->publicationFull($publication->load('category'));
    }
}
