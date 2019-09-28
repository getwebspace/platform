<?php

namespace App\Application\Actions\Cup;

use App\Application\Actions\Action;

class TradeMasterAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        switch ($this->request->getParam('action', null)) {
            case 'update':
                /** @var \App\Application\TradeMaster $trademaster */
                $trademaster = $this->container->get('trademaster');
                $trademaster->catalog_update();

                return $this->response->withAddedHeader('Location', '/cup/catalog/category');
        }

        return $this->response->withAddedHeader('Location', '/cup');
    }
}
