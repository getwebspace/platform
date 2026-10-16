<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\DescribesPublications;
use App\Domain\McpTools\Concerns\SanitizesHtml;
use App\Domain\Service\Publication\PublicationService;

class PublicationUpdateTool extends AbstractMcpTool
{
    use DescribesPublications;
    use SanitizesHtml;

    public const NAME = 'publication_update';
    public const TITLE = 'Update publication';
    public const DESCRIPTION = 'Changes a publication (uuid from publication_list): title, short / full text (HTML, scripts removed), SEO. Pass only the fields to change. '
        . 'publish=true shows a draft on the site right now, publish_at schedules it, unpublish=true turns it back into a draft. The address is kept unless "address" is passed. '
        . 'A published publication is live: get explicit confirmation of the text before changing it.';
    public const READ_ONLY = false;
    public const SCOPE = 'publication';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'uuid' => $string,
                'title' => $string,
                'short' => ['type' => 'string', 'description' => 'Short text, HTML'],
                'full' => ['type' => 'string', 'description' => 'Full text, HTML'],
                'meta_title' => $string,
                'meta_description' => $string,
                'meta_keywords' => $string,
                'publish' => ['type' => 'boolean', 'description' => 'Show on the site now'],
                'publish_at' => ['type' => 'string', 'description' => 'YYYY-MM-DD HH:MM - show from this moment'],
                'unpublish' => ['type' => 'boolean', 'description' => 'Hide from the site (back to draft)'],
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

        $moves = array_filter([!empty($args['publish']), !blank($args['publish_at'] ?? null), !empty($args['unpublish'])]);

        if (count($moves) > 1) {
            throw new \InvalidArgumentException('Pass one of publish, publish_at, unpublish');
        }

        /** @var PublicationService $publicationService */
        $publicationService = $this->container->get(PublicationService::class);
        $publication = $publicationService->read(['uuid' => $args['uuid']]);

        $data = [];

        if (array_key_exists('title', $args)) {
            if (blank(trim((string) $args['title']))) {
                throw new \InvalidArgumentException('Field title cannot be empty');
            }

            $data['title'] = trim((string) $args['title']);
        }
        if (array_key_exists('short', $args) || array_key_exists('full', $args)) {
            $content = (array) $publication->content;

            foreach (['short', 'full'] as $key) {
                if (array_key_exists($key, $args)) {
                    $content[$key] = $this->sanitizeHtml((string) $args[$key]);
                }
            }

            $data['content'] = $content;
        }

        $meta = array_filter([
            'title' => $args['meta_title'] ?? null,
            'description' => $args['meta_description'] ?? null,
            'keywords' => $args['meta_keywords'] ?? null,
        ], fn ($value) => $value !== null);

        if ($meta) {
            $data['meta'] = array_merge((array) $publication->meta, array_map('trim', $meta));
        }
        if (!empty($args['publish'])) {
            $data['date'] = datetime()->format('Y-m-d H:i:s');
        } elseif (!empty($args['unpublish'])) {
            $data['date'] = self::DRAFT_DATE;
        } elseif (!blank($args['publish_at'] ?? null)) {
            $value = trim((string) $args['publish_at']);

            if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value) !== 1 || strtotime($value) === false) {
                throw new \InvalidArgumentException('Field publish_at must be YYYY-MM-DD or YYYY-MM-DD HH:MM');
            }

            $data['date'] = datetime($value)->format('Y-m-d H:i:s');
        }

        $changed = array_keys($data);
        $addressChange = !blank($args['address'] ?? null);

        if (!$data && !$addressChange) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        // address generation on update would move the page to another URL
        $data['address'] = $addressChange ? trim((string) $args['address']) : $publication->address;

        $publication = $publicationService->update($publication, $data);

        $this->container->get(PubSub::class)->publish('api:publication:edit', $publication);
        $this->logger->notice('Publication updated via MCP', ['uuid' => (string) $publication->uuid, 'fields' => $changed]);

        return $this->publicationFull($publication->load('category')) + ['changed_fields' => $addressChange ? [...$changed, 'address'] : $changed];
    }
}
