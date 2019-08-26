<?php

namespace App\Domain\Filters\Traits;

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
            $pageRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\App\Domain\Entities\Page::class);

            /** @var \App\Domain\Entities\Page $page */
            $page = $pageRepository->findOneBy(['address' => str_escape($data[$field])]);

            return $page === null || (!empty($data['uuid']) && $page->uuid === $data['uuid']);
        };
    }
}
