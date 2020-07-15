<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form\Data;

use App\Application\Actions\Cup\Form\FormAction;
use App\Domain\Service\Form\FormService;
use App\Domain\Service\Form\DataService as FormDataService;

class DataListAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $formService = FormService::getWithContainer($this->container);
            $form = $formService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($form) {
                $formDataService = FormDataService::getWithContainer($this->container);
                $list = $formDataService->read(['form_uuid' => $form->getUuid()]);

                return $this->respondWithTemplate('cup/form/view/list.twig', [
                    'form' => $form,
                    'list' => $list,
                ]);
            }
        }

        return $this->response->withRedirect('/cup/form');
    }
}
