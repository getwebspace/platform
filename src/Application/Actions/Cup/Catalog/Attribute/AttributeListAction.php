<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Attribute;

use App\Application\Actions\Cup\Catalog\CatalogAction;

class AttributeListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $list = $this->catalogAttributeService->read();

        return $this->respondWithTemplate('cup/catalog/attribute/index.twig', [
            'attributes' => $list,
        ]);
    }
}
