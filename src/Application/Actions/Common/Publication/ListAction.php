<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Publication;

use App\Domain\AbstractAction;
use App\Domain\Exceptions\HttpBadRequestException;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\Exception\PublicationNotFoundException;
use App\Domain\Service\Publication\PublicationService;

class ListAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        /** @var PublicationCategoryService $publicationCategoryService */
        $publicationCategoryService = $this->container->get(PublicationCategoryService::class);

        /** @var PublicationService $publicationService */
        $publicationService = $this->container->get(PublicationService::class);

        try {
            $args = $this->parsePath();
            $categories = $publicationCategoryService->read();

            switch ($args['address']) {
                // publication category
                case '':
                    $category = $categories->firstWhere('address', $args['category']);
                    $order = $category->sort['by'] ?? \App\Domain\References\Publication::ORDER_BY_DATE;
                    $direction = $category->sort['direction'] ?? \App\Domain\References\Publication::ORDER_DIRECTION_ASC;

                    $query = \App\Domain\Models\Publication::query();
                    $query->whereIn('category_uuid', $category->nested()->pluck('uuid'));
                    $query->where('date', '<=', datetime()->toDateTimeString());
                    $query->orderBy($order, $direction);
                    $query->limit($category->pagination);
                    $query->offset($args['offset']);

                    $count = $query->count();
                    $publications = $query->get()->forPage($args['offset'], $category->pagination);

                    return $this->respond($category->template['list'] ?? 'publication.list.twig', [
                        'categories' => $categories->where('is_public', true),
                        'category' => $category,
                        'publications' => $publications,
                        'pagination' => [
                            'count' => $count,
                            'page' => $category->pagination,
                            'offset' => $args['offset'],
                        ],
                    ]);

                // publication
                default:
                    try {
                        $publication = $publicationService->read(['address' => $args['address']]);

                        return $this->respond($category->template['full'] ?? 'publication.full.twig', [
                            'categories' => $categories->where('is_public', true),
                            'category' => $publication->category,
                            'publication' => $publication,
                        ]);
                    } catch (PublicationNotFoundException $e) {
                        return $this->respond('p404.twig')->withStatus(404);
                    }
            }
        } catch (HttpBadRequestException $e) {
            return $this->respond('p400.twig')->withStatus(400);
        }
    }

    /**
     * @throws HttpBadRequestException
     */
    protected function parsePath(): array
    {
        $category = $this->args['category'] ?? '';
        $args = $this->args['args'] ?? '';
        $offset = 0;

        // split args
        $parts = $args !== '' ? explode('/', $args) : [];

        // check last element is digits
        if ($parts && ctype_digit(end($parts))) {
            $offset = (int) array_pop($parts);
        }

        $addressParts = array_merge([$category], $parts);
        $address = implode('/', $addressParts);

        return [
            'category' => $category,
            'address' => $address,
            'offset' => $offset,
        ];
    }
}
