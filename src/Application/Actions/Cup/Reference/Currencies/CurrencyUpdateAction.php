<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference\Currencies;

use App\Application\Actions\Cup\Reference\ReferenceAction;
use App\Domain\Service\Reference\Exception\MissingTitleValueException;
use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;
use App\Domain\Service\Reference\Exception\TitleAlreadyExistsException;
use App\Domain\Types\ReferenceTypeType;

class CurrencyUpdateAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $ref = $this->referenceService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                if ($this->isPost()) {
                    try {
                        $this->doUpdateAction($ref, ReferenceTypeType::TYPE_CURRENCY);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect('/cup/reference/currencies');

                            default:
                                return $this->respondWithRedirect('/cup/reference/currencies/' . $ref->getUuid() . '/edit');
                        }
                    } catch (TitleAlreadyExistsException $e) {
                        $this->addError('title', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/reference/currencies/form.twig', [
                    'item' => $ref,
                ]);
            } catch (ReferenceNotFoundException $e) {
                return $this->respondWithRedirect('/cup/reference/currencies');
            }
        }

        return $this->respondWithRedirect('/cup/reference/currencies');
    }
}
