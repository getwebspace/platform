<?php

namespace Application\Actions\Cup\Form;

use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class FormListAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->formRepository->findAll());

        return $this->respondRender('cup/form/index.twig', ['list' => $list]);
    }
}
