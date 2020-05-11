<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Service\Page\PageService;
use Psr\Container\ContainerInterface;

class DynamicPageAction extends AbstractAction
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $publicationCategoryRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $publicationRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $fileRepository;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->publicationCategoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Publication\Category::class);
        $this->publicationRepository = $this->entityManager->getRepository(\App\Domain\Entities\Publication::class);
    }

    protected function action(): \Slim\Http\Response
    {
        $path = ltrim($this->resolveArg('args'), '/');
        $offset = 0;

        if (preg_match('/\/(?<offset>\d)$/', $path, $matches)) {
            $offset = explode('/', $path);
            $offset = +end($offset);
            $path = str_replace('/' . $offset, '', $path);
        }

        // страницы
        $pageService = PageService::getFromContainer($this->container);
        if (($page = $pageService->read(['address' => $path])) !== null) {
            return $this->respondWithTemplate($page->getTemplate(), ['page' => $page]);
        }

        $categories = collect($this->publicationCategoryRepository->findAll());

        // категории публикаций
        if ($categories->firstWhere('address', $path)) {
            $category = $categories->firstWhere('address', $path);

            $publications = collect($this->publicationRepository->findBy(
                ['category' => \App\Domain\Entities\Publication\Category::getChildren($categories, $category)->pluck('uuid')->all()],
                [$category->sort['by'] => $category->sort['direction']],
                $category->pagination,
                $category->pagination * $offset
            ));

            return $this->respondWithTemplate($category->template['list'], [
                'categories' => $categories->where('public', true),
                'category' => $category,
                'publications' => $publications,
                'pagination' => [
                    'count' => $this->publicationRepository->count([
                        'category' => \App\Domain\Entities\Publication\Category::getChildren($categories, $category)->pluck('uuid')->all(),
                    ]),
                    'page' => $category->pagination,
                    'offset' => $offset,
                ],
            ]);
        }

        // публикация
        if ($this->publicationRepository->count(['address' => $path])) {
            $category = $categories->filter(function ($model) use ($path) {
                return mb_strpos($path, $model->address) !== false;
            })->first();

            if ($category) {
                $publication = $this->publicationRepository->findOneBy(['address' => $path]);

                return $this->respondWithTemplate($category->template['full'], [
                    'categories' => $categories->where('public', true),
                    'category' => $category,
                    'publication' => $publication,
                ]);
            }
        }

        return $this->respondWithTemplate('p404.twig')->withStatus(404);
    }
}
