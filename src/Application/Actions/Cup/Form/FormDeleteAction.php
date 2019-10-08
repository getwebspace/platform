<?php

namespace App\Application\Actions\Cup\Form;

class FormDeleteAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\Form $item */
            $item = $this->formRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty() && $this->request->isPost()) {
                $this->entityManager->remove($item);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/form')->withStatus(301);
    }
}
