<?php

namespace App\Domain\Tasks\TradeMaster;

use App\Domain\Tasks\Task;
use Psr\Container\ContainerInterface;

class DownloadImageTask extends Task
{
    /**
     * @var \App\Application\TradeMaster
     */
    protected $trademaster;

    protected function action()
    {
        $this->trademaster = $this->container->get('trademaster');
        $this->status_done();
    }
}
