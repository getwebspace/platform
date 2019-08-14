<?php

namespace Filter\Traits;

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

            /** @var \Entity\Form $publication */
            $publication = $app->getContainer()->get(\Resource\Form::class)->search(['address' => str_escape($data[$field])])->first();

            return $publication === null || (!empty($data['uuid']) && $publication->uuid === $data['uuid']);
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
