<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form\Data;

use App\Application\Actions\Cup\Form\FormAction;

class DataListAction extends FormAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $form = $this->formService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($form) {
                $list = $this->formDataService->read([
                    'form_uuid' => $form->getUuid(),
                    'limit' => 1000,
                ]);

                return $this->respondWithTemplate('cup/form/view/list.twig', [
                    'form' => $form,
                    'list' => $list,
                ]);
            }
        }

        return $this->respondWithRedirect('/cup/form');
    }
}
