<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form;

class FormListAction extends FormAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/form/index.twig', [
            'list' => $this->formService->read(['order' => ['title' => 'asc']]),
        ]);
    }
}
