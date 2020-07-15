<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form;

use App\Domain\Service\Form\FormService;

class FormListAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        $formService = FormService::getWithContainer($this->container);
        $list = $formService->read();

        return $this->respondWithTemplate('cup/form/index.twig', ['list' => $list]);
    }
}
