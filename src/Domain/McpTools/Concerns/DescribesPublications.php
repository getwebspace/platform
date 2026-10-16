<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

use App\Domain\Models\Publication;

/**
 * A publication has no status: it is shown on the site when its date has come,
 * so a draft is a publication dated far in the future
 */
trait DescribesPublications
{
    public const DRAFT_DATE = '2099-12-31 00:00:00';

    protected function isDraft(Publication $publication): bool
    {
        return datetime($publication->date)->isAfter(datetime()->addYears(50));
    }

    protected function publicationCard(Publication $publication): array
    {
        $date = datetime($publication->date);

        return [
            'uuid' => (string) $publication->uuid,
            'title' => $publication->title,
            'state' => $this->isDraft($publication) ? 'draft' : ($date->isFuture() ? 'scheduled' : 'published'),
            // the relation is not loaded / the category may be gone: not a hard error for a card
            'category' => $publication->getRelationValue('category')?->title,
            'date' => $this->isDraft($publication) ? null : $date->format('Y-m-d H:i'),
            'address' => $publication->address,
        ];
    }

    protected function publicationFull(Publication $publication): array
    {
        return $this->publicationCard($publication) + [
            'category_uuid' => (string) $publication->category_uuid,
            'short' => (string) ($publication->content['short'] ?? ''),
            'full' => (string) ($publication->content['full'] ?? ''),
            'meta' => $publication->meta,
            'url' => rtrim((string) $this->parameter('common_homepage', ''), '/') . '/' . ltrim((string) $publication->address, '/'),
        ];
    }
}
