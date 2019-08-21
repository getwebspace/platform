<?php

namespace Application\Actions\Common;

use Application\Actions\Action;
use Psr\Container\ContainerInterface;

class DynamicPageAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $pageRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $publicationCategoryRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $publicationRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->pageRepository = $this->entityManager->getRepository(\Domain\Entities\Page::class);
        $this->publicationCategoryRepository = $this->entityManager->getRepository(\Domain\Entities\Publication\Category::class);
        $this->publicationRepository = $this->entityManager->getRepository(\Domain\Entities\Publication::class);
    }

    protected function action(): \Slim\Http\Response
    {
        $path = ltrim($this->resolveArg('args'), '/');
        $offset = 0;

        if (preg_match('/\/(?<offset>\d)$/', $path, $matches)) {
            $offset = explode('/', $path);
            $offset = +end($offset);
            $path = str_replace('/' . $offset , '', $path);
        }

        if ($this->pageRepository->count(['address' => $path])) {
            $page = $this->pageRepository->findOneBy(['address' => $path]);

            return $this->respondRender($page->template, ['page' => $page]);
        } else {
            $categories = collect($this->publicationCategoryRepository->findAll());

            if ($this->publicationCategoryRepository->count(['address' => $path])) {
                $category = $categories->where('address', $path)->first();

                $publications = collect($this->publicationRepository->findBy(
                    ['category' => $categories->where('parent', $category->uuid)->pluck('uuid')->merge([$category->uuid])->map(function ($item) { return strval($item); })->all()],
                    [$category->sort['by'] => $category->sort['direction']],
                    $category->pagination,
                    $category->pagination * $offset
                ));

                return $this->respondRender($category->template['list'], ['categories' => $categories, 'category' => $category, 'publications' => $publications]);
            } else {
                $category = $categories->filter(function ($model) use ($path) { return strpos($path, $model->address) !== false; })->first();

                if ($category) {
                    $path = str_replace($category->address . '/', '', $path);
                    $publication = $this->publicationRepository->findOneBy(['address' => $path]);

                    return $this->respondRender($category->template['full'], ['publication' => $publication, 'categories' => $categories, 'category' => $category]);
                }
            }
        }

        return $this->respondRender('p404.twig')->withStatus(404);
    }
}
