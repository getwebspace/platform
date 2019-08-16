<?php

namespace Domain\Filters\Traits;

use Slim\App;

trait FormFilterRules
{
    /**
     * Проверяет уникальность адреса формы
     *
     * @return \Closure
     */
    public function UniqueFormAddress()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $formRepository */
            $formRepository = $app->getContainer()->get(\Doctrine\ORM\EntityManager::class)->getRepository(\Domain\Entities\Form::class);

            /** @var \Domain\Entities\Page $form */
            $form = $formRepository->findOneBy(['address' => str_escape($data[$field])]);

            return $form === null || (!empty($data['uuid']) && $form->uuid === $data['uuid']);
        };
    }

    /**
     * Обрабатывает поле Origin, проверяет и формирует строки
     *
     * @return \Closure
     */
    public function ValidFormOrigin()
    {
        return function (&$data, $field) {
            $values = &$data[$field]; // array !
            $checker = new \AEngine\Validator\Check\Url();

            foreach ($values as $index => $url) {
                if ($checker($values, $index) === false) { // kostil
                    return false;
                }
            }

            return true;
        };
    }
}
