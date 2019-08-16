<?php

namespace Application\Actions\Cup\Form\Data;

use Application\Actions\Cup\Form\FormAction;
use Psr\Container\ContainerInterface;
use Slim\Http\Response;

class DataDeleteAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if (
            $this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid')) &&
            $this->resolveArg('data') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('data'))
        ) {
            /** @var \Domain\Entities\Form\Data $item */
            $item = $this->dataRepository->findOneBy([
                'form_uuid' => $this->resolveArg('uuid'),
                'uuid' => $this->resolveArg('data'),
            ]);

            if (!$item->isEmpty()) {
                if (!$item->isEmpty() && $this->request->isPost()) {
                    $this->entityManager->remove($item);
                    $this->entityManager->flush();
                }
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/form/' . $this->resolveArg('uuid') . '/view');
    }
}
