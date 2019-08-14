<?php

namespace Domain\Filters\Traits;

use Slim\App;

trait PublicationFilterRules
{
    /**
     * Проверяет уникальность адреса публикации
     *
     * @return \Closure
     */
    public function UniquePublicationAddress()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Entity\Publication $publication */
            $publication = $app->getContainer()->get(\Resource\Publication::class)->search(['address' => str_escape($data[$field])])->first();

            return $publication === null || (!empty($data['uuid']) && $publication->uuid === $data['uuid']);
        };
    }

    /**
     * Проверяет опрос публикации
     *
     * @return \Closure
     */
    public function ValidPublicationPoll()
    {
        return function (&$data, $field) {
            $buf = [
                'question' => '',
                'answer' => '',
            ];
            $value = &$data[$field];

            if (!is_array($value)) {
                $value = $buf;

                return true;
            }

            if (isset($value['question'])) {
                $buf['question'] = $value['question'];
            }
            if (isset($value['answer'])) {
                $buf['answer'] = $value['answer'];
            }

            $value = $buf;

            return true;
        };
    }

    /**
     * Проверяет содержимое публикаций
     *
     * @return \Closure
     */
    public function ValidPublicationContent()
    {
        return function (&$data, $field) {
            $buf = [
                'short' => '',
                'full' => '',
            ];
            $value = &$data[$field];

            if (!is_array($value)) {
                $value = $buf;

                return true;
            }

            if (isset($value['short'])) {
                $buf['short'] = $value['short'];
            }
            if (isset($value['full'])) {
                $buf['full'] = $value['full'];
            }
            if (!$buf['full'] && $buf['short']) {
                $buf['full'] = $buf['short'];
            }

            $value = $buf;

            return true;
        };
    }

    /**
     * Проверяет уникальность адреса категории
     *
     * @return \Closure
     */
    public function UniqueCategoryAddress()
    {
        return function (&$data, $field) {
            /** @var App $app */
            $app = $GLOBALS['app'];

            /** @var \Entity\Publication\Category $category */
            $category = $app->getContainer()->get(\Resource\Publication\Category::class)->search(['address' => str_escape($data[$field])])->first();

            return $category === null || (!empty($data['uuid']) && $category->uuid === $data['uuid']);
        };
    }

    /**
     * Проверяет поле sort
     *
     * @return \Closure
     */
    public function ValidCategorySort()
    {
        return function (&$data, $field) {
            $buf = [
                'by' => \Reference\Publication\Category::ORDER_BY_DATE,
                'direction' => \Reference\Publication\Category::ORDER_DIRECTION_ASC,
            ];
            $value = &$data[$field];

            if (!is_array($value)) {
                $value = $buf;

                return true;
            }

            if (isset($value['by'])) {
                $buf['by'] = $value['by'];
            }
            if (isset($value['direction'])) {
                $buf['direction'] = $value['direction'];
            }

            $value = $buf;

            return true;
        };
    }

    /**
     * Проверяет настройки шаблонов категории публикаций
     *
     * @return \Closure
     */
    public function ValidCategoryTemplate()
    {
        return function (&$data, $field) {
            $buf = [
                'list' => '',
                'short' => '',
                'full' => '',
            ];
            $value = &$data[$field];

            if (!is_array($value)) {
                $value = $buf;

                return true;
            }

            if (isset($value['list'])) {
                $buf['list'] = $value['list'];
            }
            if (isset($value['short'])) {
                $buf['short'] = $value['short'];
            }
            if (isset($value['full'])) {
                $buf['full'] = $value['full'];
            }

            $value = $buf;

            return true;
        };
    }
}
