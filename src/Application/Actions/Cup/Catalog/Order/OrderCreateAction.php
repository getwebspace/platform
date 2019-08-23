<?php

namespace Application\Actions\Cup\Catalog\Order;

use AEngine\Support\Str;
use Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class OrderCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondRender('cup/catalog/order/form.twig');
    }
}
