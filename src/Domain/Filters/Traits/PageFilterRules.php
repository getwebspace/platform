<?php

namespace Domain\Filters\Traits;

use AEngine\Support\Str;
use Ramsey\Uuid\Uuid;
use Slim\App;

trait PageFilterRules
{
    /**
     * Проверяет уникальность адреса публикации
     *
     * @return \Closure
     */
    public function UniquePageAddress()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $pageRepository */
            $pageRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\Domain\Entities\Page::class);

            /** @var \Domain\Entities\Page $page */
            $page = $pageRepository->findOneBy(['address' => str_escape($data[$field])]);

            return $page === null || (!empty($data['uuid']) && $page->uuid === $data['uuid']);
        };
    }
}
