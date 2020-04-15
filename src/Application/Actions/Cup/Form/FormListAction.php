<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form;

class FormListAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        $list = collect($this->formRepository->findAll());

        return $this->respondWithTemplate('cup/form/index.twig', ['list' => $list]);
    }
}
