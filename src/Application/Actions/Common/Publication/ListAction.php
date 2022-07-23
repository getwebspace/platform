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
            $params = $this->parsePath();
            $categories = $publicationCategoryService->read();

            switch ($params['address']) {
                // publication category
                case '':
                    $category = $categories->firstWhere('address', $params['category']);
                    $childrenCategories = $category->getNested($categories)->pluck('uuid')->all();
                    $order = $category->sort['by'] ?? \App\Domain\References\Publication::ORDER_BY_DATE;
                    $direction = $category->sort['direction'] ?? \App\Domain\References\Publication::ORDER_DIRECTION_ASC;

                    $qb = $this->entityManager->createQueryBuilder();
                    $query = $qb
                        ->from(\App\Domain\Entities\Publication::class, 'p')
                        ->where('p.category IN (:category)')
                        ->andWhere('p.date <= :now')
                        ->setParameter('category', $childrenCategories)
                        ->setParameter('now', datetime('now', 'UTC'))
                        ->orderBy('p.' . $order, $direction)
                        ->setFirstResult($params['offset'] * $category->pagination)
                        ->setMaxResults($category->pagination);

                    $publications = collect($query->select('p')->getQuery()->getResult());
                    $count = $query->select('COUNT(p)')->getQuery()->getSingleScalarResult();

                    return $this->respond($category->template['list'] ?? 'publication.list.twig', [
                        'categories' => $categories->where('public', true),
                        'category' => $category,
                        'publications' => $publications,
                        'pagination' => [
                            'count' => $count,
                            'page' => $category->pagination,
                            'offset' => $params['offset'],
                        ],
                    ]);

                // publication
                default:
                    try {
                        $publication = $publicationService->read(['address' => $params['address']]);
                        $category = $categories->firstWhere('uuid', $publication->getCategory()->getUuid());

                        return $this->respond($category->template['full'] ?? 'publication.full.twig', [
                            'categories' => $categories->where('public', true),
                            'category' => $category,
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
        $category = $this->resolveArg('category');
        $path = ltrim(str_replace('/' . $category, '', $this->request->getUri()->getPath()), '/');
        $parts = $path ? explode('/', $path) : [];
        $offset = 0;

        if ($parts && ($buf = $parts[count($parts) - 1]) && ctype_digit($buf)) {
            $offset = +$buf;
            unset($parts[count($parts) - 1]);
        }

        return [
            'category' => $category,
            'address' => implode('/', $parts ? array_merge([$category], $parts) : []),
            'offset' => $offset,
        ];
    }
}
