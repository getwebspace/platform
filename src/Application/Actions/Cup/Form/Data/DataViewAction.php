<?php

namespace App\Application\Actions\Cup\Form\Data;

use App\Application\Actions\Cup\Form\FormAction;

class DataViewAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if (
            $this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid')) &&
            $this->resolveArg('data') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('data'))
        ) {
            /** @var \App\Domain\Entities\Form\Data $item */
            $item = $this->dataRepository->findOneBy([
                'form_uuid' => $this->resolveArg('uuid'),
                'uuid' => $this->resolveArg('data'),
            ]);

            if (!$item->isEmpty()) {
                return $this->respondRender('cup/form/view/detail.twig', [
                    'item' => $item,
                ]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/form')->withStatus(301);
    }
}
