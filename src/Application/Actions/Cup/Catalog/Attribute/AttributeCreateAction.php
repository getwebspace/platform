<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Attribute;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Service\Catalog\Exception\MissingTitleValueException;
use App\Domain\Service\Catalog\Exception\TitleAlreadyExistsException;

class AttributeCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $attribute = $this->catalogAttributeService->create([
                    'title' => $this->request->getParam('title'),
                    'address' => $this->request->getParam('address'),
                    'type' => $this->request->getParam('type'),
                ]);

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withRedirect('/cup/catalog/attribute');

                    default:
                        return $this->response->withRedirect('/cup/catalog/attribute/' . $attribute->getUuid() . '/edit');
                }
            } catch (TitleAlreadyExistsException|MissingTitleValueException $e) {
                $this->addError('title', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/catalog/attribute/form.twig');
    }
}
