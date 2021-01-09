<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Attribute;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;

class AttributeUpdateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('attribute') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('attribute'))) {
            $attribute = $this->catalogAttributeService->read([
                'uuid' => $this->resolveArg('attribute'),
            ]);

            if ($attribute) {
                if ($this->request->isPost()) {
                    try {
                        $attribute = $this->catalogAttributeService->update($attribute, [
                            'title' => $this->request->getParam('title'),
                            'type' => $this->request->getParam('type'),
                        ]);

                        switch (true) {
                            case $this->request->getParam('save', 'exit') === 'exit':
                                return $this->response->withRedirect('/cup/catalog/attribute');
                            default:
                                return $this->response->withRedirect('/cup/catalog/attribute/' . $attribute->getUuid() . '/edit');
                        }
                    } catch (TitleAlreadyExistsException | MissingTitleValueException $e) {
                        $this->addError('title', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/catalog/attribute/form.twig', [
                    'attribute' => $attribute,
                ]);
            }
        }

        return $this->response->withRedirect('/cup/catalog/attribute');
    }
}
