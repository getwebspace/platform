<?php

namespace App\Application\Actions\Cup\Form;

class FormListAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->formRepository->findAll());

        return $this->respondRender('cup/form/index.twig', ['list' => $list]);
    }
}
