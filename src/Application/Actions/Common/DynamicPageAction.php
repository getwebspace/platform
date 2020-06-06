<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\Exception\PublicationNotFoundException;
use App\Domain\Service\Publication\PublicationService;

class DynamicPageAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        $path = ltrim($this->resolveArg('args'), '/');
        $offset = 0;

        if (preg_match('/\/(?<offset>\d)$/', $path, $matches)) {
            $offset = explode('/', $path);
            $offset = +end($offset);
            $path = str_replace('/' . $offset, '', $path);
        }

        $pageService = PageService::getWithContainer($this->container);
        $publicationCategoryService = PublicationCategoryService::getWithContainer($this->container);
        $publicationService = PublicationService::getWithContainer($this->container);

        try {
            // страницы
            if (($page = $pageService->read(['address' => $path])) !== null) {
                return $this->respondWithTemplate($page->getTemplate(), ['page' => $page]);
            }
        } catch (PageNotFoundException $e) {
            // ignore
        }

        $categories = $publicationCategoryService->read();

        // категории публикаций
        if ($categories->count() && $categories->firstWhere('address', $path)) {
            $category = $categories->firstWhere('address', $path);
            $childrenCategories = \App\Domain\Entities\Publication\Category::getChildrenCategories($categories, $category)->pluck('uuid')->all();

            return $this->respondWithTemplate($category->template['list'], [
                'categories' => $categories->where('public', true),
                'category' => $category,
                'publications' => $publicationService->read([
                    ['category' => $childrenCategories],
                    'order' => [$category->sort['by'] => $category->sort['direction']],
                    'limit' => $category->pagination,
                    'offset' => $category->pagination * $offset,
                ]),
                'pagination' => [
                    'count' => $publicationService->count(['category' => $childrenCategories]),
                    'page' => $category->pagination,
                    'offset' => $offset,
                ],
            ]);
        }

        try {
            // публикация
            if (($publication = $publicationService->read(['address' => $path])) !== null) {
                $category = $categories->firstWhere('uuid', $publication->getUuid()->toString());

                if ($category) {
                    return $this->respondWithTemplate($category->template['full'], [
                        'categories' => $categories->where('public', true),
                        'category' => $category,
                        'publication' => $publication,
                    ]);
                }
            }
        } catch (PublicationNotFoundException $e) {
            // ignore
        }

        return $this->respondWithTemplate('p404.twig')->withStatus(404);
    }
}
