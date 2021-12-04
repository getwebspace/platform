<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Attribute;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;

class AttributeUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('attribute') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('attribute'))) {
            $attribute = $this->catalogAttributeService->read([
                'uuid' => $this->resolveArg('attribute'),
            ]);

            if ($attribute) {
                if ($this->isPost()) {
                    try {
                        $attribute = $this->catalogAttributeService->update($attribute, [
                            'title' => $this->getParam('title'),
                            'address' => $this->getParam('address'),
                            'type' => $this->getParam('type'),
                        ]);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect('/cup/catalog/attribute');

                            default:
                                return $this->respondWithRedirect('/cup/catalog/attribute/' . $attribute->getUuid() . '/edit');
                        }
                    } catch (TitleAlreadyExistsException $e) {
                        $this->addError('title', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/catalog/attribute/form.twig', [
                    'attribute' => $attribute,
                ]);
            }
        }

        return $this->respondWithRedirect('/cup/catalog/attribute');
    }
}
