<?php

namespace Application\Actions\Cup\Form\Data;

use Application\Actions\Cup\Form\FormAction;

class DataViewAction extends FormAction
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
                $files = $this->fileRepository->findBy([
                    'item' => \Domain\Types\FileItemType::ITEM_FORM_DATA,
                    'item_uuid' => $this->resolveArg('data'),
                ]);

                return $this->respondRender('cup/form/view/detail.twig', [
                    'item' => $item,
                    'files' => $files,
                ]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/form');
    }
}
