<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference\Currencies;

use App\Application\Actions\Cup\Reference\ReferenceAction;
use App\Domain\Service\Reference\Exception\MissingTitleValueException;
use App\Domain\Service\Reference\Exception\TitleAlreadyExistsException;
use App\Domain\Types\ReferenceTypeType;

class CurrencyCreateAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            try {
                $ref = $this->doCreateAction(ReferenceTypeType::TYPE_CURRENCY);

                switch (true) {
                    case $this->getParam('save', 'exit') === 'exit':
                        return $this->respondWithRedirect('/cup/reference/currencies');

                    default:
                        return $this->respondWithRedirect('/cup/reference/currencies/' . $ref->getUuid() . '/edit');
                }
            } catch (MissingTitleValueException|TitleAlreadyExistsException $e) {
                $this->addError('title', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/reference/currencies/form.twig');
    }
}
