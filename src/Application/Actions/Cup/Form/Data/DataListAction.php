<?php

namespace Application\Actions\Cup\Form\Data;

use Application\Actions\Cup\Form\FormAction;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class DataListAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \Domain\Entities\Form $item */
            $item = $this->formRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty()) {
                $list = collect($this->dataRepository->findBy(['form_uuid' => $item->uuid]));

                return $this->respondRender('cup/form/view/list.twig', [
                    'form' => $item,
                    'list' => $list,
                ]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/form');
    }
}
