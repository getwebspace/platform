<?php

namespace Plugin\TradeMaster;

use App\Application\Plugin;
use Psr\Container\ContainerInterface;

class TradeMasterPluginTwigExt extends \Twig\Extension\AbstractExtension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getName()
    {
        return 'tm_plugin';
    }

    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction('tm_api', [$this, 'tm_api']),
        ];
    }

    public function tm_api($endpoint, array $params = [], $method = 'GET')
    {
        \RunTracy\Helpers\Profiler\Profiler::start('twig:fn:tm_api');

        $trademaster = $this->container->get('trademaster');
        $result = $trademaster->api([
            'endpoint' => $endpoint,
            'params' => $params,
            'method' => $method,
        ]);

        \RunTracy\Helpers\Profiler\Profiler::finish('twig:fn:tm_api (%s)', $endpoint, ['endpoint' => $endpoint, 'params' => $params, 'method' => $method]);

        return $result;
    }
}
