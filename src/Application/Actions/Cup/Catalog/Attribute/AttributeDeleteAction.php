<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Attribute;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class AttributeDeleteAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('attribute') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('attribute'))) {
            $attribute = $this->catalogAttributeService->read([
                'uuid' => $this->resolveArg('attribute')
            ]);

            if ($attribute) {
                $this->catalogAttributeService->delete($attribute);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:attribute:delete', $attribute);
            }

        }

        return $this->respondWithRedirect('/cup/catalog/attribute');
    }
}
